<?php
/*
 * StreamAction for content reputation wall
 * @author Anton Kurnitzky
 */
class ReputationStreamAction extends ContentContainerStreamAction
{
    /**
     * Sort by reputation_content value
     * The content creation time is not used here
     */
    const SORT_HOT = 'h';

    /**
     * Sort by reputation_content value_time:
     * The content creation_time is used here
     * Posts loose 50% of their score in one week (exponential degression)
     */
    const SORT_TOP = 't';

    /**
     * Sort by reputation_content value
     * Only show posts younger than 24 hours
     */
    const SORT_NEW = 'n';

    /**
     * Sort by reputation_content value_time:
     * Similar to SORT_TOP but here degression is faster.
     * A post loses 50% of it's score in 24 hours
     */
    const SORT_RISING = 'r';

    public function setupCriteria()
    {
        $this->criteria->alias = 'wall_entry';
        $this->criteria->join = 'LEFT JOIN content ON wall_entry.content_id = content.id';
        $this->criteria->join .= ' LEFT JOIN reputation_content on content.id=reputation_content.content_id';
        $this->criteria->join .= ' LEFT JOIN user creator ON creator.id = content.created_by';
        $this->criteria->limit = 6;
        $this->criteria->condition = 'creator.status=' . User::STATUS_ENABLED;
        $this->criteria->condition .= " AND content.object_model != 'Activity'";

        /**
         * Setup Sorting
         */
        if ($this->sort == self::SORT_TOP) {
            $this->criteria->order = 'reputation_content.score DESC';
            if ($this->from != "") {
                $this->criteria->condition .= " AND reputation_content.score <=
                (SELECT rc.score FROM reputation_content rc
                LEFT JOIN wall_entry we on rc.content_id = we.content_id
                WHERE we.id=" . $this->from . ")";
            }
        } else if ($this->sort == self::SORT_NEW) {


            $this->criteria->condition .= " AND content.created_at >= DATE_SUB(NOW(), INTERVAL 36 HOUR)";
            $this->criteria->order = 'reputation_content.score DESC';
            if ($this->from != "") {
                $this->criteria->condition .= " AND reputation_content.score <=
            (SELECT rc.score FROM reputation_content rc
            LEFT JOIN wall_entry we on rc.content_id = we.content_id
            WHERE we.id=" . $this->from . ")";
            }
        } else if ($this->sort == self::SORT_RISING) {
            $this->criteria->order = 'reputation_content.score_short DESC';
            if ($this->from != "") {
                $this->criteria->condition .= " AND reputation_content.score_short <=
                (SELECT rc.score_short FROM reputation_content rc
                LEFT JOIN wall_entry we on rc.content_id = we.content_id
                WHERE we.id=" . $this->from . ")";
            }
        } else {
            $this->criteria->order = 'reputation_content.score_long DESC';
            if ($this->from != "") {
                $this->criteria->condition .= " AND reputation_content.score_long <=
                (SELECT rc.score_long FROM reputation_content rc
                LEFT JOIN wall_entry we on rc.content_id = we.content_id
                WHERE we.id=" . $this->from . ")";
            }
        }
    }
}