<?php
declare(strict_types=1);

namespace MVQN\Synchronization;




final class MapResults
{

    /** @var string[] */
    private $created;

    /** @var string[] */
    private $updated;

    /** @var string[] */
    private $deleted;

    /** @var string[] */
    private $missing;

    /** @var string[] */
    private $duplicated;


    public function __construct()
    {
        $this->created = [];
        $this->updated = [];
        $this->deleted = [];
        $this->missing = [];
        $this->duplicated = [];
    }

    public function __toString()
    {
        return json_encode(get_object_vars($this));
    }


    /**
     * @return string[]
     */
    public function getCreated(): array
    {
        return $this->created;
    }

    /**
     * @param string $key
     * @return MapResults
     */
    public function addCreated(string $key): self
    {
        $this->created[] = $key;
        return $this;
    }

    /**
     * @param int $index
     * @return MapResults
     */
    public function delCreated(int $index): self
    {
        // Delete the specified item and reindex the array.
        unset($this->created[$index]);
        $this->created = array_values($this->created);

        return $this;
    }

    /**
     * @return string[]
     */
    public function getUpdated(): array
    {
        return $this->updated;
    }

    /**
     * @param string $key
     * @return MapResults
     */
    public function addUpdated(string $key): self
    {
        $this->updated[] = $key;
        return $this;
    }

    /**
     * @param int $index
     * @return MapResults
     */
    public function delUpdated(int $index): self
    {
        // Delete the specified item and reindex the array.
        unset($this->updated[$index]);
        $this->updated = array_values($this->updated);

        return $this;
    }

    /**
     * @return string[]
     */
    public function getDeleted(): array
    {
        return $this->deleted;
    }

    /**
     * @param string $key
     * @return MapResults
     */
    public function addDeleted(string $key): self
    {
        $this->deleted[] = $key;
        return $this;
    }

    /**
     * @param int $index
     * @return MapResults
     */
    public function delDeleted(int $index): self
    {
        // Delete the specified item and reindex the array.
        unset($this->deleted[$index]);
        $this->deleted = array_values($this->deleted);

        return $this;
    }


    /**
     * @return string[]
     */
    public function getMissing(): array
    {
        return $this->missing;
    }

    /**
     * @param string $key
     * @return MapResults
     */
    public function addMissing(string $key): self
    {
        $this->missing[] = $key;
        return $this;
    }

    /**
     * @param string $key
     * @return MapResults
     */
    public function delMissing(string $key): self
    {
        if(in_array($key, $this->missing))
        {
            // Delete the specified item and reindex the array.
            unset($this->missing[array_search($key, $this->missing)]);
            $this->missing = array_values($this->missing);
        }

        return $this;
    }

    /**
     * @return string[]
     */
    public function getDuplicated(): array
    {
        return $this->duplicated;
    }

    /**
     * @param string $key
     * @return MapResults
     */
    public function addDuplicated(string $key): self
    {
        $this->duplicated[] = $key;
        return $this;
    }

    /**
     * @param int $index
     * @return MapResults
     */
    public function delDuplicated(int $index): self
    {
        // Delete the specified item and reindex the array.
        unset($this->duplicated[$index]);
        $this->duplicated = array_values($this->duplicated);

        return $this;
    }








}