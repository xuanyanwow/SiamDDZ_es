<?php


namespace App\HttpController;

class Index extends Base
{

    public function index()
    {
        // $file = EASYSWOOLE_ROOT.'/vendor/easyswoole/easyswoole/src/Resource/Http/welcome.html';
        // if(!is_file($file)){
        //     $file = EASYSWOOLE_ROOT.'/src/Resource/Http/welcome.html';
        // }
        // $this->response()->write(file_get_contents($file));

        $this->response()->withHeader('Content-Type', 'text/html; charset=utf-8');

        $this->response()->write(file_get_contents(EASYSWOOLE_ROOT."/player.html"));
    }

    protected function actionNotFound(?string $action)
    {
        $this->response()->withStatus(404);
        $file = EASYSWOOLE_ROOT.'/vendor/easyswoole/easyswoole/src/Resource/Http/404.html';
        if(!is_file($file)){
            $file = EASYSWOOLE_ROOT.'/src/Resource/Http/404.html';
        }
        $this->response()->write(file_get_contents($file));
    }
}