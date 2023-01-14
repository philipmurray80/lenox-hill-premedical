<?php
namespace Slim;

use Slim\View;

class ViewWithLayout extends View
{
    protected $layout;

    public function setLayout($layout) {
        $this->layout = $layout;
    }

    public function render($template, $data = NULL) {
        if ($this->layout) {
            $content = parent::render($template);
            $this->setData(array('content' => $content));
            return parent::render($this->layout);
        } else {
            return parent::render($template);
        }
    }
}