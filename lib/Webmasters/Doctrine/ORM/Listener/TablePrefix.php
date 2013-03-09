<?php

namespace Webmasters\Doctrine\ORM\Listener;

use \Doctrine\ORM\Event\LoadClassMetadataEventArgs;

class TablePrefix
{
    protected $_prefix = '';

    public function __construct($prefix)
    {
        $this->_prefix = (string)$prefix;
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs)
    {
        $prefix = $this->_prefix;

        $classMetadata = $eventArgs->getClassMetadata();
        $classMetadata->setPrimaryTable(
            array('name' => $prefix . $classMetadata->getTableName())
        );

        foreach ($classMetadata->getAssociationMappings() as $fieldName => $mapping) {
            if ($mapping['type'] == \Doctrine\ORM\Mapping\ClassMetadataInfo::MANY_TO_MANY) {
                $mappedTableName = $classMetadata->associationMappings[$fieldName]['joinTable']['name'];
                $classMetadata->associationMappings[$fieldName]['joinTable']['name'] = $prefix . $mappedTableName;
            }
        }
    }
}
