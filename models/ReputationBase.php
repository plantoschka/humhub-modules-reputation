<?php

/**
 * Base class for reputation models
 * @author Anton Kurnitzky
 */
class ReputationBase extends HActiveRecord
{

    // Default caching time is 15 minutes
    const CACHE_TIME_SECONDS = 900;

    // Default space reputation settings
    const LOGARITHMIC = 0;
    const LINEAR = 1;
    const DEFAULT_FUNCTION = ReputationUser::LOGARITHMIC;
    const DEFAULT_LOGARITHM_BASE = '100';
    const DEFAULT_CREATE_CONTENT = '1';
    const DEFAULT_SMB_LIKES_CONTENT = '2';
    const DEFAULT_SMB_FAVORITES_CONTENT = '2';
    const DEFAULT_SMB_COMMENTS_CONTENT = '3';
    const DEFAULT_DAILY_LIMIT = '15';
    const DEFAULT_DECREASE_WEIGHTING = '1';
    const DEFAULT_CRON_JOB = '1';
    const DEFAULT_LAMBDA_SHORT = '0.00120338';
    const DEFAULT_LAMBDA_LONG = '0.000024558786159';
    const DEFAULT_RANKING_NEW_PERIOD = '36';

    /**
     * Returns all content objects (posts, polls, etc.) from this space
     *
     * @param $spaceId : The id of the space
     * @param bool $forceUpdate : Ignore cache
     * @return Content[]
     */
    public function getContentFromSpace($spaceId, $forceUpdate = false)
    {
        $cacheId = 'posts_created_cache' . '_' . $spaceId;

        $spaceContent = Yii::app()->cache->get($cacheId);

        if ($spaceContent === false || $forceUpdate === true) {

            $criteria = new CDbCriteria();
            $criteria->condition = 'space_id=:spaceId AND object_model!=:activity';
            $criteria->params = array(':spaceId' => $spaceId, ':activity' => 'Activity');

            $spaceContent = Content::model()->findAll($criteria);

            Yii::app()->cache->set($cacheId, $spaceContent, ReputationContent::CACHE_TIME_SECONDS);
        }

        return $spaceContent;
    }

    /**
     * Count all comments a content object has received.
     *
     * @param Content $content : The content object
     * @param $userId : The user id
     * @param $cacheId : The cache id
     * @param bool $countOwnComments : Count comments created by same user as content
     * @param bool $forceUpdate : true if cache should be ignored
     * @return Comment[]
     */
    public function getCommentsFromContent(Content $content, $userId, $cacheId, $countOwnComments = false, $forceUpdate = false)
    {
        $comments = Yii::app()->cache->get($cacheId);

        if ($comments === false || $forceUpdate === true) {
            $objectModel = strtolower($content->object_model);
            $comments = array();

            try {
                $criteria = new CDbCriteria;
                $criteria->alias = 'c';
                $criteria->join = 'LEFT JOIN ' . $objectModel . ' o ON c.object_id = o.id';
                $criteria->join .= ' LEFT JOIN content ct ON o.id=ct.object_id';
                if ($countOwnComments === true) {
                    $criteria->condition = 'ct.id=:contentId AND ct.created_by=:userId AND c.object_model=ct.object_model';
                } else {
                    $criteria->condition = 'ct.id=:contentId AND ct.created_by=:userId AND c.created_by!=:userId AND c.object_model=ct.object_model';
                }
                $criteria->params = array(':contentId' => $content->id, ':userId' => $userId);

                $comments = Comment::model()->findAll($criteria);

                Yii::app()->cache->set($cacheId, $comments, ReputationBase::CACHE_TIME_SECONDS);
            } catch (Exception $e) {
                Yii::trace('Couldn\'t count comments from object model: ' . $objectModel);
            }
        }

        return $comments;
    }

    /**
     * Return an array with all space settings
     * @param $space
     * @return array
     */
    protected function getSpaceSettings($space)
    {
        $function = SpaceSetting::Get($space->id, 'functions', 'reputation', ReputationBase::DEFAULT_FUNCTION);
        $logarithmBase = SpaceSetting::Get($space->id, 'logarithm_base', 'reputation', ReputationBase::DEFAULT_LOGARITHM_BASE);
        $create_content = SpaceSetting::Get($space->id, 'create_content', 'reputation', ReputationBase::DEFAULT_CREATE_CONTENT);
        $smb_likes_content = SpaceSetting::Get($space->id, 'smb_likes_content', 'reputation', ReputationBase::DEFAULT_SMB_LIKES_CONTENT);
        $smb_favorites_content = SpaceSetting::Get($space->id, 'smb_favorites_content', 'reputation', ReputationBase::DEFAULT_SMB_FAVORITES_CONTENT);
        $smb_comments_content = SpaceSetting::Get($space->id, 'smb_comments_content', 'reputation', ReputationBase::DEFAULT_SMB_COMMENTS_CONTENT);
        $daily_limit = SpaceSetting::Get($space->id, 'daily_limit', 'reputation', ReputationBase::DEFAULT_DAILY_LIMIT);
        $decrease_weighting = SpaceSetting::Get($space->id, 'decrease_weighting', 'reputation', ReputationBase::DEFAULT_DECREASE_WEIGHTING);
        $lambda_short = SpaceSetting::Get($space->id, 'lambda_short', 'reputation', ReputationBase::DEFAULT_LAMBDA_SHORT);
        $lambda_long = SpaceSetting::Get($space->id, 'lambda_long', 'reputation', ReputationBase::DEFAULT_LAMBDA_LONG);
        $ranking_new_period = SpaceSetting::Get($space->id, 'ranking_new_period', 'reputation', ReputationBase::DEFAULT_RANKING_NEW_PERIOD);

        $spaceSettings = array($function, $logarithmBase, $create_content, $smb_likes_content,
            $smb_favorites_content, $smb_comments_content, $daily_limit, $decrease_weighting,
            $lambda_short, $lambda_long, $ranking_new_period);

        return $spaceSettings;
    }

    /**
     * Count all likes a content object has received. Do not count likes from user who created this post
     *
     * @param Content $content : The content object
     * @param $userId : The user id
     * @param $cacheId : The cache id
     * @param bool $forceUpdate : true if cache should be ignored
     * @return Like[]
     */
    protected function getLikesFromContent(Content $content, $userId, $cacheId, $forceUpdate = false)
    {
        $likes = Yii::app()->cache->get($cacheId);

        if ($likes === false || $forceUpdate === true) {
            $objectModel = strtolower($content->object_model);
            $likes = array();

            // comments have to be handled otherwise
            if (strcmp($objectModel, 'comment') == 0) {
                return array();
            }

            try {
                $criteria = new CDbCriteria;
                $criteria->alias = 'l';
                $criteria->join = 'LEFT JOIN ' . $objectModel . ' p ON l.object_id = p.id';
                $criteria->join .= ' LEFT JOIN content ct ON p.id=ct.object_id';
                $criteria->condition = 'ct.id=:contentId AND l.created_by!=:userId AND ct.created_by=:userId AND l.object_model=:objectModel AND ct.object_model=:objectModel';
                $criteria->params = array(':contentId' => $content->id, ':objectModel' => $objectModel, ':userId' => $userId);

                $likes = Like::model()->findAll($criteria);

                Yii::app()->cache->set($cacheId, $likes, ReputationBase::CACHE_TIME_SECONDS);
            } catch (Exception $e) {
                Yii::trace('Couldn\'t fetch likes from object model: ' . $objectModel);
            }
        }

        return $likes;
    }

    /**
     * Count all favorites a content object has received. Do not count favorites from user who created this post
     *
     * @param Content $content : The content object
     * @param $userId : The user id
     * @param $cacheId : The cache id
     * @param bool $forceUpdate : true if cache should be ignored
     * @return Favorite[]
     */
    protected function getFavoritesFromContent(Content $content, $userId, $cacheId, $forceUpdate = false)
    {
        $favorites = Yii::app()->cache->get($cacheId);

        if ($favorites === false || $forceUpdate === true) {
            $objectModel = strtolower($content->object_model);
            $favorites = array();

            // not possible to favorite comments atm
            if (strcmp($objectModel, 'comment') == 0) {
                return array();
            }

            try {
                $criteria = new CDbCriteria;
                $criteria->alias = 'f';
                $criteria->join = 'LEFT JOIN ' . $objectModel . ' p ON f.object_id = p.id';
                $criteria->join .= ' LEFT JOIN content ct ON p.id=ct.object_id';
                $criteria->condition = 'ct.id=:contentId AND f.created_by!=:userId AND ct.created_by=:userId AND f.object_model=:objectModel AND ct.object_model=:objectModel';
                $criteria->params = array(':contentId' => $content->id, ':objectModel' => $objectModel, ':userId' => $userId);

                $favorites = Favorite::model()->findAll($criteria);

                Yii::app()->cache->set($cacheId, $favorites, ReputationBase::CACHE_TIME_SECONDS);
            } catch (Exception $e) {
                Yii::trace('Couldn\'t fetch favorites from object model: ' . $objectModel);
            }
        }

        return $favorites;
    }
}