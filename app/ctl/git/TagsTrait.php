<?php
namespace firegit\app\ctl\git;

trait TagsTrait
{
    function tags_action()
    {
        $branches = $this->repo->listBranches();//分支列表
        $tags = $this->repo->listTags();//标签列表
        $this->setBranch();
        $this->response->set(array(
            'navType' => 'tags',
            'tage' => $tags,
            'branchelist' => $branches,
        ))->setView('git/tags.phtml');
    }
    function _add_tags_action()
    {
        $datas = $this->posts('orig', 'remark', 'tagname');
        $code = $this->repo->addTag($datas['orig'], $datas['tagname']);
        if($code === 0){
            return 'ok';
        }else{
            throw new \Exception('tags.error');
        }

    }


}