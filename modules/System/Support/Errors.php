<?php
/**
 * User: 刘单风
 * DateTime: 2016/6/6 11:33
 * CopyRight：医库软件PHP小组
 */

namespace Modules\System\Support;

use Illuminate\Support\MessageBag;

trait Errors {

    /**
     * The Message Bag instance
     *
     * @var \Illuminate\Support\MessageBag
     */
    protected $errors;

    public function addError($attribute = '', $msg = '')
    {
        $this->passes();

        $this->errors->add($attribute, $msg);

        return false;
    }

    public function passes()
    {
        if( $this->errors ) return;

        $this->errors = new MessageBag;
    }

    public function errors()
    {
        $this->passes();
        return $this->errors;
    }
}