<?php

/*
 * The space configuration form
 *
 * @author Anton Kurnitzky
 */

class ConfigureForm extends CFormModel
{
    public $functions;
    public $logarithmBase;
    public $create_content;
    public $smb_likes_content;
    public $smb_favorites_content;
    public $smb_comments_content;
    public $daily_limit;
    public $decrease_weighting;
    public $cron_job;
    // advanced settings
    public $lambda_long; // ranking hot
    public $lambda_short; // ranking rising

    /**
     * Declares the validation rules.
     */
    public function rules()
    {
        return array(
            array('functions', 'required'),
            array('logarithmBase', 'required'),
            array('logarithmBase', 'numerical', 'min' => 1),
            array('create_content', 'required'),
            array('create_content', 'numerical', 'min' => 0),
            array('smb_likes_content', 'required'),
            array('smb_likes_content', 'numerical', 'min' => 0),
            array('smb_favorites_content', 'required'),
            array('smb_favorites_content', 'numerical', 'min' => 0),
            array('smb_comments_content', 'required'),
            array('smb_comments_content', 'numerical', 'min' => 0),
            array('daily_limit', 'required'),
            array('smb_comments_content', 'numerical', 'min' => 0),
            array('decrease_weighting', 'required'),
            array('cron_job', 'required'),
            array('lambda_long', 'required'),
            array('lambda_long', 'type', 'type'=>'float'),
            array('lambda_short', 'required'),
            array('lambda_short', 'type', 'type'=>'float'),
        );
    }

    /**
     * Declares customized attribute labels.
     * If not declared here, an attribute would have a label that is
     * the same as its name with the first letter in upper case.
     */
    public function attributeLabels()
    {
        return array(
            'functions' => Yii::t('ReputationModule.forms_configureForm', 'Function'),
            'logarithmBase' => Yii::t('ReputationModule.forms_configureForm', 'Logarithm base'),
            'create_content' => Yii::t('ReputationModule.forms_configureForm', 'Creating posts or comments'),
            'smb_likes_content' => Yii::t('ReputationModule.forms_configureForm', 'Somebody liked the post'),
            'smb_favorites_content' => Yii::t('ReputationModule.forms_configureForm', 'Somebody marked the post as favorite'),
            'smb_comments_content' => Yii::t('ReputationModule.forms_configureForm', 'Somebody comments the post'),
            'daily_limit' => Yii::t('ReputationModule.forms_configureForm', 'Daily limit for Users'),
            'decrease_weighting' => Yii::t('ReputationModule.forms_configureForm', 'Decrease weighting per post'),
            'cron_job' => Yii::t('ReputationModule.forms_configureForm', 'Update reputation data on hourly cron job'),
            'lambda_long' => Yii::t('ReputationModule.forms_configureForm', 'Exponential decrease for Ranking Hot'),
            'lambda_short' => Yii::t('ReputationModule.forms_configureForm', 'Exponential decrease for Ranking Rising'),
        );
    }
}