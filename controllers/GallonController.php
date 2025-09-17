<?php

class GallonController
{
    private $gallonModel;

    public function __construct($gallonModel)
    {
        $this->gallonModel = $gallonModel;
    }

    public function getAllGallons()
    {
        return $this->gallonModel->getAll();
    }

    public function getGallon($id)
    {
        return $this->gallonModel->getById($id);
    }

    public function createGallon($request)
    {
        return $this->gallonModel->create($request);
    }

    public function updateGallon($request, $id)
    {
        return $this->gallonModel->update($id, $request);
    }

    public function deleteGallon($id)
    {
        return $this->gallonModel->delete($id);
    }
}
