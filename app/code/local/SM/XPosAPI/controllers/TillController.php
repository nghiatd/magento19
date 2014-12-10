<?php
class SM_XPosAPI_TillController extends SM_XPosAPI_Controller_AbstractController
{
    public function indexAction()
    {
        $this->_outputCachedFile("till");
    }
}