<?php
class SM_XPosAPI_CategoryController extends SM_XPosAPI_Controller_AbstractController
{
    public function indexAction()
    {
        $this->_outputCachedFile('category');
    }
}