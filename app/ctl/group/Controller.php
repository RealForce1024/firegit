<?php
namespace firegit\app\ctl\group;

class Controller extends \firegit\http\Controller
{
    function _before()
    {
        $this->set('mainNav', 'group');
        $this->setLayout('layout/common.phtml');
    }

    function index_action($group = '')
    {
        if (!$group) {
            $groups = \firegit\git\Manager::getGroups();
            $this->response->set(array(
                'groups' => $groups
            ))
            ->setView('group/index.phtml');
            return;
        }
        $repos = \firegit\git\Manager::getReposByGroup($group);

        $this->response->set(array(
            'repos' => $repos,
            'git' => array(
                'group' => $group
            ),
            'prefix' => '/'.$group.'/',
        ));

        $this->setView('group/group.phtml');
    }
}