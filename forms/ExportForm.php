<?php

/*
 * The csv export form
 *
 * @author Anton Kurnitzky
 */

class ExportForm extends CFormModel
{
    public $weighting;

    /**
     * Declares the validation rules.
     */
    public function rules()
    {
        return array(
            array('weighting', 'required'),
            array('weighting', 'numerical', 'min' => 1),
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
            'weighting' => Yii::t('ReputationModule.forms_exportForm', 'Weighting'),
        );
    }
}