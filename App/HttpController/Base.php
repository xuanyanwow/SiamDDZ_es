<?php

namespace App\HttpController;


use App\Exceptions\AuthException;
use EasySwoole\HttpAnnotation\AnnotationController;
use EasySwoole\HttpAnnotation\Exception\Annotation\ParamValidateError;
use EasySwoole\Validate\Validate;

class Base extends AnnotationController
{
    public function onException(\Throwable $throwable): void
    {
        if($throwable instanceof ParamValidateError){
            /** @var Validate $validate */
            $validate = $throwable->getValidate();
            $errorMsg = $validate->getError()->getErrorRuleMsg();
            $errorCol = $validate->getError()->getField();
            $this->writeJson(400,null,"{$errorCol}{$errorMsg}");
        }else{
            $this->writeJson(500,null,$throwable->getMessage());
        }
    }

    public function who()
    {
        $token = $this->request()->getRequestParam("token");
        if (!$token){
            throw new AuthException("Auth who must be post token field");
        }

        return base64_decode($token);
    }
}