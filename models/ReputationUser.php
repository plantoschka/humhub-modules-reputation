<?php

/**
 * This is the model class for table "reputation_user".
 *
 * The followings are the available columns in table 'reputation_user':
 * @property integer $id
 * @property integer $value
 * @property integer $visibility
 * @property integer $user_id
 * @property integer $space_id
 * @property string $created_at
 * @property integer $created_by
 * @property string $updated_at
 * @property integer $updated_by
 *
 * @author Anton Kurnitzky
 */
class ReputationUser extends ReputationBase
{
    /*
     * Key-Value pair
     * Key: YY-MM-DD
     * Value: The reputation score the user gained on this day
     */
    private $daily_reputation = array();

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return 'reputation_user';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return array(
            array('value, visibility, user_id, space_id', 'required'),
            array('value, visibility, user_id, space_id, created_by, updated_by', 'numerical', 'integerOnly' => true),
            array('created_at, updated_at', 'safe'),
        );
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        return array(
            'user' => array(self::BELONGS_TO, 'User', 'user_id'),
            'space' => array(self::BELONGS_TO, 'Space', 'space_id'),
        );
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return array(
            'id' => 'ID',
            'value' => 'Value',
            'visibility' => 'Visibility',
            'user_id' => 'User',
            'space_id' => 'Space',
            'created_at' => 'Created At',
            'created_by' => 'Created By',
            'updated_at' => 'Updated At',
            'updated_by' => 'Updated By',
        );
    }

    /**
     * Updates all user reputation for this space
     * @param $space : The space to check
     * @param bool $forceUpdate : Ignore cache
     */
    public function updateUserReputation($space, $forceUpdate = false)
    {
        $spaceId = $space->id;

        // get all users from this space
        $attributes = array('space_id' => $spaceId);
        $spaceUsers = SpaceMembership::model()->findAllByAttributes($attributes);

        foreach ($spaceUsers as $user) {

            $cacheId = 'reputation_space_user' . '_' . $spaceId . '_' . $user->user_id;
            $userReputation = Yii::app()->cache->get($cacheId);

            if ($userReputation === false || $forceUpdate === true) {

                // get all reputation_user objects from this space
                $attributes = array('user_id' => $user->user_id, 'space_id' => $spaceId);
                $userReputation = ReputationUser::model()->findByAttributes($attributes);

                if ($userReputation == null && !Yii::app()->user->isGuest) {
                    // Create new reputation_user entry
                    $userReputation = new ReputationUser;
                    $userReputation->user_id = $user->user_id;
                    $userReputation->space_id = $spaceId;
                    $userReputation->visibility = 0;
                    $userReputation->created_by = $user->user_id;

                }
                $userReputation->value = $this->calculateUserReputationScore($user->user_id, $space, $forceUpdate);
                $userReputation->updated_at = date('Y-m-d H:i:s');

                $userReputation->save();

                Yii::app()->cache->set($cacheId, $userReputation, ReputationBase::CACHE_TIME_SECONDS);
            }
        }

        $this->deleteMissingUsers($spaceId);
    }

    /**
     * Returns the static model of the specified AR class.
     * Please note that you should have this exact method in all your CActiveRecord descendants!
     * @param string $className active record class name.
     * @return ReputationUser the static model class
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    /**
     * Calculate the user reputation score inside a space
     * Use likes, favorites, comments from content that user created
     * Use posted comments and likes received for this comments for content the user didn't create
     * Include limitations in calculate like weight decrease and daily limit
     *
     * @param $userId : The userId to calculate reputation for
     * @param $space : The space the calculation is being based on
     * @return int: User reputation score inside this space
     */
    private function calculateUserReputationScore($userId, $space, $forceUpdate = false)
    {
        $spaceSettings = $this->getSpaceSettings($space); // array containg all space settings
        $dailyLimit = $spaceSettings[6]; // max points a user can receive on one single day
        $decreaseWeighting = $spaceSettings[7];  // should weighting of repeating actions be decreased

        $spaceContent = $this->getContentFromSpace($space->id, $forceUpdate);

        foreach ($spaceContent as $content) {
            /*
             * keep track of how many times an content object was liked, favorited etc.
             * this allows to decrease the value of repeating actions
             * e.g. the second like only gives the user half the points from the first like
             */
            $scoreCount = 1;

            /*
             * handle content that is created by this user
             * use likes, favorites, comments from content that user created
             */
            if ($content->created_by == $userId) {
                $this->addToDailyReputation($content, $spaceSettings[2], $dailyLimit);

                // now count the likes this content received from other users
                $cacheId = 'likes_earned_cache_' . $userId . '_' . $content->id;
                $likes = $this->getLikesFromContent($content, $userId, $cacheId, $forceUpdate);
                foreach ($likes as $like) {
                    if ($decreaseWeighting == '1') {
                        $this->addToDailyReputation($like, $spaceSettings[3] / $scoreCount, $dailyLimit);
                    } else {
                        $this->addToDailyReputation($like, $spaceSettings[3], $dailyLimit);
                    }

                    $scoreCount++;
                }

                if ($space->isModuleEnabled('favorite')) {
                    $scoreCount = 1;
                    // now count the favorites this content received from other users
                    $cacheId = 'favorites_earned_cache_' . $userId . '_' . $content->id;
                    $favorites = $this->getFavoritesFromContent($content, $userId, $cacheId, $forceUpdate);
                    foreach ($favorites as $favorite) {
                        if ($decreaseWeighting == '1') {
                            $this->addToDailyReputation($favorite, $spaceSettings[4] / $scoreCount, $dailyLimit);
                        } else {
                            $this->addToDailyReputation($favorite, $spaceSettings[4], $dailyLimit);
                        }

                        $scoreCount++;
                    }
                }

                $scoreCount = 1;

                // now count how many comments this post has generated
                $cacheId = 'comments_earned_cache_' . $userId . '_' . $content->id;
                $comments = $this->getCommentsFromContent($content, $userId, $cacheId, false, $forceUpdate);
                foreach ($comments as $comment) {
                    if ($decreaseWeighting == '1') {
                        $this->addToDailyReputation($comment, $spaceSettings[5] / $scoreCount, $dailyLimit);
                    } else {
                        $this->addToDailyReputation($comment, $spaceSettings[5], $dailyLimit);
                    }

                    $scoreCount++;
                }

                $scoreCount = 1;
            }

            /**
             * now handle posts that were created by others users
             * The user gets points for comments he created and for likes the comments have received
             */
            $commentsPosted = $this->GetCommentsGeneratedByUser($userId, $content, $forceUpdate);
            foreach ($commentsPosted as $commentPosted) {
                $this->addToDailyReputation($commentPosted, $spaceSettings[2], $dailyLimit);
            }

            $commentsLiked = $this->GetCommentsGeneratedByUserLikedByOthers($userId, $content, $forceUpdate);
            foreach ($commentsLiked as $commentLiked) {
                if ($decreaseWeighting == '1') {
                    $this->addToDailyReputation($commentLiked, $spaceSettings[3] / $scoreCount, $dailyLimit);
                } else {
                    $this->addToDailyReputation($commentLiked, $spaceSettings[3], $dailyLimit);
                }
                $scoreCount++;
            }
        }

        /*
         * Iterate over daily_reputation structure to get final score
         */
        $reputationScore = 0;
        foreach ($this->daily_reputation as $reputation) {
            $reputationScore += $reputation->getScore();
        }

        // reset this array for next user
        $this->daily_reputation = array();

        return $this->calculateUserScore($spaceSettings[0], $reputationScore, $spaceSettings[1]);
    }

    /**
     * @param $content
     * @param $scoreToAdd
     * @param $daily_limit
     * @return array
     */
    private function addToDailyReputation($content, $scoreToAdd, $daily_limit)
    {
        $date = date_create($content->created_at)->format('Y-m-d');

        if (array_key_exists($date, $this->daily_reputation)) {
            $currentDate = $this->daily_reputation[$date];
            $currentDate->addScore($scoreToAdd);

            return array($date, $currentDate, $this->daily_reputation);
        } else {
            $this->daily_reputation[$date] = new DailyReputation($scoreToAdd, $daily_limit);
        }
    }

    /**
     * Get all comments the user created.
     *
     * @param $userId
     * @param Content $content
     * @param $forceUpdate : Ignore cache
     * @return Comment[]
     */
    public function GetCommentsGeneratedByUser($userId, Content $content, $forceUpdate = false)
    {
        $cacheId = 'comments_generated_cache_' . $userId . '_' . $content->id;

        $commentsGenerated = Yii::app()->cache->get($cacheId);

        if ($commentsGenerated === false || $forceUpdate === true) {
            $object_model = strtolower($content->object_model);

            $commentsGenerated = array();

            try {
                $criteria = new CDbCriteria;
                $criteria->alias = 'c';
                $criteria->join = 'LEFT JOIN ' . $object_model . ' o ON c.object_id = o.id';
                $criteria->join .= ' LEFT JOIN content ct ON o.id=ct.object_id';
                $criteria->condition = 'ct.id=:contentId AND c.created_by=:userId AND c.object_model=ct.object_model';
                $criteria->params = array(':contentId' => $content->id, ':userId' => $userId);

                $commentsGenerated = Comment::model()->findAll($criteria);

                Yii::app()->cache->set($cacheId, $commentsGenerated, ReputationBase::CACHE_TIME_SECONDS);

            } catch (Exception $e) {
                Yii::trace('Couldn\'t count generated comments from object model: ' . $object_model);
            }
        }


        return $commentsGenerated;
    }

    /**
     * Get all likes that a user got for a comment he made
     * When a user likes his own comment it will not be counted
     *
     * @param $userId
     * @param Content $content
     * @param $forceUpdate : Ignore cache
     * @return int
     */
    public function GetCommentsGeneratedByUserLikedByOthers($userId, Content $content, $forceUpdate)
    {
        $cacheId = 'comments_liked_cache_' . $userId . '_' . $content->id;

        $commentsLiked = Yii::app()->cache->get($cacheId);

        if ($commentsLiked === false || $forceUpdate === true) {
            $object_model = strtolower($content->object_model);
            $commentsLiked = array();

            try {
                $criteria = new CDbCriteria;
                $criteria->alias = 'l';
                $criteria->join = 'LEFT JOIN comment c ON c.id=l.object_id';
                $criteria->join .= ' LEFT JOIN ' . $object_model . ' o ON c.object_id = o.id';
                $criteria->join .= ' LEFT JOIN content ct ON o.id=ct.object_id';
                $criteria->condition = 'l.object_model=\'comment\' AND l.created_by!=:userId AND ct.id=:contentId AND c.created_by=:userId AND c.object_model=ct.object_model';
                $criteria->params = array(':contentId' => $content->id, ':userId' => $userId);

                $commentsLiked = Like::model()->findAll($criteria);

                Yii::app()->cache->set($cacheId, $commentsLiked, ReputationBase::CACHE_TIME_SECONDS);

            } catch (Exception $e) {
                Yii::trace('Couldn\'t count generated comments from object model: ' . $object_model);
            }
        }


        return $commentsLiked;
    }

    /**
     * Calculate final user score.
     *
     * @param $function : Linear or Logarithmic
     * @param $reputationScore : The score the user reached
     * @param $logarithmBase : The logarithm base
     * @return int
     */
    private function calculateUserScore($function, $reputationScore, $logarithmBase)
    {
        if ($function == ReputationBase::LINEAR) {
            return intval($reputationScore);
        } else {
            if ($reputationScore == 0) {
                return 0;
            } else {
                // increase reputation score + 1 so log is not 0 when user has 1 point
                $logValue = log($reputationScore + 1, $logarithmBase);
            }

            return intval(round($logValue * 100));
        }
    }

    /**
     * Delete users that are not space members anymore
     *
     * @param $spaceId
     * @throws CDbException
     */
    private function deleteMissingUsers($spaceId)
    {
        $attributes = array('space_id' => $spaceId);
        $reputationUsers = ReputationUser::model()->findAllByAttributes($attributes);
        foreach ($reputationUsers as $user) {
            $criteria = new CDbCriteria;
            $criteria->condition = 'space_id=:spaceId AND user_id=:userId';
            $criteria->params = array(':spaceId' => $spaceId, ':userId' => $user->user_id);

            if (SpaceMembership::model()->count($criteria) <= 0) {
                $user->delete();
            }
        }
    }

    /**
     * Get a array containing user e-mail addresses and their reputation score for a specific space
     * @param $spaceId
     * @return array()
     */
    public function getSpaceUsersAndScore($spaceId)
    {
        $reputationUsers = Yii::app()->db->createCommand()
            ->select('u.email, ru.value')
            ->from('reputation_user ru')
            ->join('user u', 'ru.user_id=u.id')
            ->where('space_id=:spaceId', array(':spaceId' => $spaceId))
            ->queryAll();

        return $reputationUsers;
    }
}