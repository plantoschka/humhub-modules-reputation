<?php

/**
 * Drop created tables on module uninstall
 * @author Anton Kurnitzky
 */
class uninstall extends ZDbMigration
{
    public function up()
    {
        $this->dropTable('reputation_user');
        $this->dropTable('reputation_content');
    }

    public function down()
    {
        echo "uninstall does not support migration down.\n";
        return false;
    }
}