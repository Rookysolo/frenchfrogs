<?php namespace FrenchFrogs\Business;

/**
 * Overload Eloquent model for better use
 *
 * Class Business
 *
 * @package FrenchFrogs\Business
 */
abstract class Business
{

    /**
     * Set to TRUE if Business is managed with UUID as primary key
     *
     * @var bool
     */
    static protected $is_uuid = true;

    /**
     * Primary key
     *
     * @var mixed
     */
    protected $id;


    /**
     * Model
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $model;


    /**
     * Class Name of the main model
     *
     * @var string
     */
     static protected $modelClass;

    /**
     * Constructor
     *
     * @param $id
     */
    public function __construct($id)
    {
        $this->id = static::isUuid() ? uuid($id)->bytes : $id;
    }


    /**
     * factory
     *
     * @param $id
     * @return $this
     */
    static public function get($id)
    {
        return new static($id);
    }

    /**
     * Getter for ID
     *
     * @return mixed
     */
    public function getId($format = 'bytes')
    {
        return static::isUuid() && $format != false ? uuid($this->id)->$format : $this->id;
    }

    /**
     * Return User as an array
     *
     * @return array
     */
    public function toArray()
    {
        return  $this->getModel()->toArray();
    }

    /**
     * return the main model
     *
     * @param bool|false $reload
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function getModel($reload = false)
    {
        if (!isset($this->model) || $reload) {
            $class = static::$modelClass;
            $this->model = $class::findOrFail($this->getId());
        }

        return $this->model;
    }


    /**
     * Save the model
     *
     * @param array $data
     * @return $this
     */
    public function save(array $data)
    {
        $model = $this->getModel();
        $model->update($data);
        return $this;
    }

    /**
     * Factory
     *
     * @param $data
     * @return Business
     */
    static public function create(array $data)
    {
        $class = static::$modelClass;

        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $class;

        $model = $class::create($data);

        return static::get($model->getKey());
    }

    /**
     * destroy the select business
     *
     * @return null
     * @throws \Exception
     */
    public function destroy()
    {
        $this->getModel()->delete();
        return null;
    }


    /**
     * return true id user exist
     *
     * @param $id
     * @return bool
     */
    static public function exists($id)
    {
        try {
            $class = static::$modelClass;
            $class::findOrFail(static::isUuid() ? uuid($id)->bytes : $id);
            return true;
        } catch(\Exception $e) {
            return false;
        }
    }

    /**
     * Return true if $is_uuid id true
     *
     * @return bool
     */
    static function isUuid()
    {
        return (bool) static::$is_uuid;
    }

}