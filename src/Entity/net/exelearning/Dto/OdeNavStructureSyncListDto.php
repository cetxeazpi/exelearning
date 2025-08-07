<?php

namespace App\Entity\net\exelearning\Dto;

/**
 * OdeNavStructureSyncListDto.
 */
class OdeNavStructureSyncListDto extends BaseDto
{
    /**
     * @var string
     */
    protected $odeId;

    /**
     * @var OdeNavStructureSyncDto[]
     */
    protected $structure;

    public function __construct()
    {
        $this->structure = [];
    }

    /**
     * @return string
     */
    public function getOdeId()
    {
        return $this->odeId;
    }

    /**
     * @param string $odeSessionId
     */
    public function setOdeId($odeId)
    {
        $this->odeId = $odeId;
    }

    /**
     * @return multitype:\App\Entity\net\exelearning\Dto\OdeNavStructureDto
     */
    public function getStructure()
    {
        return $this->structure;
    }

    /**
     * @param multitype:\App\Entity\net\exelearning\Dto\OdeNavStructureDto $structure
     */
    public function setStructure($structure)
    {
        $this->structure = $structure;
    }

    /**
     * @param OdeNavStructureSyncDto $structureElem
     */
    public function addStructure($structureElem)
    {
        $this->structure[] = $structureElem;
    }
}
