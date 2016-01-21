<?php

/*
 * @author Anton Kurnitzky
 */

class m150622_112714_reputation_content extends EDbMigration
{
    public function up()
    {
        $this->createTable('reputation_content', array(
            'id' => 'pk',
            'score' => 'int(11) NOT NULL',
            'score_short' => 'float(11) NOT NULL',
            'score_long' => 'float(11) NOT NULL',
            'content_id' => 'int(11) DEFAULT NULL',
            'created_at' => 'datetime DEFAULT NULL',
            'created_by' => 'int(11) DEFAULT NULL',
            'updated_at' => 'datetime DEFAULT NULL',
            'updated_by' => 'int(11) DEFAULT NULL',
        ), '');

    }

    public function down()
    {
        echo "m150722_112714_reputation_content does not support migration down.\n";
        return false;
    }
}