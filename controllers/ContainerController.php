<?php

class ContainerController
{
    private $containerModel;

    public function __construct($containerModel)
    {
        $this->containerModel = $containerModel;
    }

    public function getAllContainers()
    {
        return $this->containerModel->getAll();
    }

    public function getContainer($id)
    {
        return $this->containerModel->getById($id);
    }

    public function createContainer($request)
    {
        return $this->containerModel->create($request);
    }

    public function updateContainer($request, $id)
    {
        return $this->containerModel->update($id, $request);
    }

    public function deleteContainer($request, $id)
    {
        return $this->containerModel->delete($id);
    }
}
