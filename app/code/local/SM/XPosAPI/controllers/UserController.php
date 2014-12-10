<?php
class SM_XPosAPI_UserController extends SM_XPosAPI_Controller_AbstractController
{
    public function indexAction()
    {
       $this->_outputCachedFile("user");
    }
}