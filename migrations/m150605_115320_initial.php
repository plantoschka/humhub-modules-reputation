<?php

/*
 * @author Anton Kurnitzky
 */

class m150605_115320_initial extends EDbMigration
{
    public function up()
    {
        $this->createTable('reputation_user', array(
            'id' => 'pk',
            'value' => 'int(11) NOT NULL',
            'visibility' => 'tinyint(4) NOT NULL',
            'user_id' => 'int(11) NOT NULL',
            'space_id' => 'int(11) NOT NULL',
            'created_at' => 'datetime DEFAULT NULL',
            'created_by' => 'int(11) DEFAULT NULL',
            'updated_at' => 'datetime DEFAULT NULL',
            'updated_by' => 'int(11) DEFAULT NULL',
        ), '');
    }

    public function down()
    {
        echo "m150605_115320_initial does not support migration down.\n";
        return false;
    }
}