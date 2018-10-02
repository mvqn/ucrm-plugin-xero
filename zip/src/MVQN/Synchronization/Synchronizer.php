<?php
declare(strict_types=1);

namespace MVQN\Synchronization;




final class Synchronizer
{


    /**
     * @param array $source
     * @param array $destination
     * @param ClassMap $sourceMap
     * @param ClassMap $destinationMap
     * @param MapResults|null $sourceChanges
     * @param MapResults|null $destinationChanges
     * @param string $mapFile
     * @return array
     * @throws \Exception
     */
    public static function map(array $source, array $destination, ClassMap $sourceMap, ClassMap $destinationMap,
        ?MapResults &$sourceChanges = null, ?MapResults &$destinationChanges = null, string $mapFile = ""
    ): array
    {
        // Load the existing JSON map file, if one exists...
        $map = file_exists($mapFile) ? json_decode(file_get_contents($mapFile), true) : [];

        // Get the source's "key" and "property" property.
        $sourceKey = $sourceMap->getKey();
        $sourceProperty = $sourceMap->getProperty();

        // Get the destination's "key" and "property" property.
        $destinationKey = $destinationMap->getKey();
        $destinationProperty = $destinationMap->getProperty();



        // Duplicate the map to be used as a reverse lookup for determining changes.
        $sourceHandled = $map;

        // Initialize the local source mapping results.
        $sourceChanges = new MapResults();

        // Loop through the provided UCRM Clients first...
        foreach($source as $class)
        {
            $currentClass = get_class($class);
            $mappingClass = $sourceMap->getClass();

            if($currentClass !== $mappingClass)
                throw new \Exception("Class '$currentClass' does not match the ClassMap class of '$mappingClass'!");

            if ($sourceKey === "")
                throw new \Exception("Key '$sourceKey' is not in a supported format!");

            $getter = "get".ucfirst($sourceProperty);

            if(!method_exists($class, $getter))
                throw new \Exception("A getter of '$getter' could not be found on the class '$class'!");

            $value = $class->{$getter}();


            $sourceName = $sourceMap->generateName($class);


            // IF the "key" already exists in previous mapping...
            if (array_key_exists($sourceName, $map))
            {
                // AND, IF the mapping already contains the mapped property...
                if(array_key_exists($sourceKey, $map[$sourceName]))
                {
                    // THEN check to see if the "property" value is the same...
                    if($map[$sourceName][$sourceKey] !== $value)
                    {
                        $map[$sourceName][$sourceKey] = $value;
                        $sourceChanges->addUpdated($sourceName);

                        // Mark this one as handled!
                        unset($sourceHandled[$sourceName]);
                    }
                    else
                    {
                        // OTHERWISE nothing has changed since the last mapping, so mark this one as handled!
                        unset($sourceHandled[$sourceName]);
                    }
                }
                else
                {
                    // OTHERWISE the mapping does not exist, so create it and add it to the list of "created" mappings!
                    $map[$sourceName][$sourceKey] = $value;
                    $sourceChanges->addCreated($sourceName);

                    // Mark this one as handled!
                    unset($sourceHandled[$sourceName]);
                }
            }
            else
            {
                $existing = "";

                // Check for matching "property" values, in case the mapping was created using a different "key"...
                foreach($map as $name => $oldMappings)
                {
                    if(array_key_exists($sourceKey, $oldMappings) && $oldMappings[$sourceKey] === $value)
                        $existing = $name;
                }

                if($existing !== "")
                {
                    // THEN we found a matching "property" value using another "key", so update the existing mapping.
                    $map[$sourceName] = $map[$existing];
                    unset($map[$existing]);
                    $sourceChanges->addUpdated($sourceName);

                    // Mark the old one as handled!
                    unset($sourceHandled[$existing]);
                }
                else
                {
                    // OTHERWISE create the mapping and add it to the list of "created" mappings!
                    $map[$sourceName] = [];
                    $map[$sourceName][$sourceKey] = $value;
                    $sourceChanges->addCreated($sourceName);

                    // Mark this one as handled!
                    unset($sourceHandled[$sourceName]);
                }
            }
        }

        // Loop through any leftover/unhandled mappings...
        foreach($sourceHandled as $name => $mappings)
        {
            if(array_key_exists($sourceKey, $mappings))
            {
                // THEN remove the source mapping and add this to the list of "deleted" mappings!
                unset($map[$name][$sourceKey]);
                $sourceChanges->addDeleted($name);
            }
            else
            {
                if(array_key_exists($destinationKey, $mappings))
                    $sourceChanges->addMissing($name);
            }

            unset($sourceHandled[$name]);
        }



        // Duplicate the map to be used as a reverse lookup for determining changes.
        $destinationHandled = $map;

        // Initialize the local destination mapping results.
        $destinationChanges = new MapResults();

        // Loop through the provided Xero Contacts next...
        foreach($destination as $class)
        {
            $currentClass = get_class($class);
            $mappingClass = $destinationMap->getClass();

            if($currentClass !== $mappingClass)
                throw new \Exception("Class '$currentClass' does not match the ClassMap class of '$mappingClass'!");

            if ($destinationKey === "")
                throw new \Exception("Key '$destinationKey' is not in a supported format!");

            $getter = "get".ucfirst($destinationProperty);

            if(!method_exists($class, $getter))
                throw new \Exception("A getter of '$getter' could not be found on the class '$class'!");

            $value = $class->{$getter}();

            $destinationName = $destinationMap->generateName($class);

            // IF the "key" already exists in previous mapping...
            if (array_key_exists($destinationName, $map))
            {
                // AND, IF the mapping already contains the mapped property...
                if(array_key_exists($destinationKey, $map[$destinationName]))
                {
                    // THEN check to see if the "property" value is the same...
                    if($map[$destinationName][$destinationKey] !== $value)
                    {
                        $map[$destinationName][$destinationKey] = $value;
                        $destinationChanges->addUpdated($destinationName);

                        // Mark this one as handled!
                        unset($destinationHandled[$destinationName]);
                    }
                    else
                    {
                        // OTHERWISE nothing has changed since the last mapping, so mark this one as handled!
                        unset($destinationHandled[$destinationName]);
                    }
                }
                else
                {
                    // OTHERWISE the mapping does not exist, so create it and add it to the list of "created" mappings!
                    $map[$destinationName][$destinationKey] = $value;
                    $destinationChanges->addCreated($destinationName);

                    // Mark this one as handled!
                    unset($destinationHandled[$destinationName]);
                }
            }
            else
            {
                $existing = "";

                // Check for matching "property" values, in case the mapping was created using a different "key"...
                foreach($map as $oldName => $oldMappings)
                {
                    if(array_key_exists($destinationKey, $oldMappings) && $oldMappings[$destinationKey] === $value)
                        $existing = $oldName;
                }

                if($existing !== "")
                {
                    // THEN we found a matching "property" value using another "key", so update the existing mapping.
                    $map[$destinationName] = $map[$existing];
                    unset($map[$existing]);
                    $destinationChanges->addUpdated($destinationName);

                    // Mark changes in source changes "missing" array, as it has been previously generated.
                    if(in_array($existing, $sourceChanges->getMissing()))
                    {
                        $sourceChanges->addMissing($destinationName);
                        $index = array_search($existing, $sourceChanges->getMissing());

                        $sourceChanges->delMissing($index);
                    }

                    // Mark the old one as handled!
                    unset($destinationHandled[$existing]);
                }
                else
                {
                    // OTHERWISE create the mapping and add it to the list of "created" mappings!
                    $map[$destinationName] = [];
                    $map[$destinationName][$destinationKey] = $value;
                    $destinationChanges->addCreated($destinationName);

                    // Mark this one as handled!
                    unset($destinationHandled[$destinationName]);
                }
            }
        }

        // Loop through any leftover/unhandled mappings...
        foreach($destinationHandled as $name => $mappings)
        {
            if(array_key_exists($destinationKey, $mappings))
            {
                // THEN remove the destination mapping and add this to the list of "deleted" mappings!
                unset($map[$name][$destinationKey]);
                $destinationChanges->addDeleted($name);
            }
            else
            {
                if(array_key_exists($sourceKey, $mappings))
                    $destinationChanges->addMissing($name);
            }

            unset($destinationHandled[$name]);
        }



        // Loop through and remove any empty mappings...
        foreach($map as $name => $mappings)
        {
            if(!array_key_exists($sourceKey, $mappings) && !array_key_exists($destinationKey, $mappings))
                unset($map[$name]);
        }

        if($mapFile !== "")
        {
            // Create the directory for the map file, as needed...
            if(!file_exists(dirname($mapFile)))
                mkdir(dirname($mapFile), 0777, true);

            // Save the results into the "data/clients.json" file for later usage.
            file_put_contents($mapFile, json_encode($map, JSON_PRETTY_PRINT));
        }

        // Finally, return the new mapping!
        return $map;
    }



}