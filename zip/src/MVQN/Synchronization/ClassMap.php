<?php
declare(strict_types=1);

namespace MVQN\Synchronization;




final class ClassMap
{

    /** @var string */
    private $class;

    /** @var string */
    private $key;

    /** @var string */
    private $property;

    /** @var callable */
    private $generator;


    public function __construct(string $class, callable $nameGenerator, string $key, string $property)
    {

        if(!class_exists($class))
            throw new \Exception("Class '$class' was not found!");

        $this->class = $class;

        $this->generator = $nameGenerator;

        //if(!property_exists($class, $key))
        //    throw new \Exception("Class '$class' does not have a property '$key'!");

        $this->key = $key;


        //if(!property_exists($class, $property))
        //    throw new \Exception("Class '$class' does not have a property '$property'!");

        $this->property = $property;

    }


    public function generateName($class): string
    {
        return call_user_func($this->generator, $class);
    }


    /**
     * @return string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return string
     */
    public function getProperty(): string
    {
        return $this->property;
    }





}