<?php namespace FrenchFrogs\Laravel\Database\Eloquent;

use FrenchFrogs\Core\Nenuphar;
use FrenchFrogs\Maker\Maker;
use FrenchFrogs\PCRE\PCRE;
use Illuminate\Support\Str;
use Webpatser\Uuid\Uuid;
use Illuminate\Database\Eloquent\Builder;


/**
 * Class Model
 *
 * @method static $this findOrNew() findOrNew($id)
 * @method static $this find() find($id)
 * @method static $this first() first()
 * @method static $this findOrFail() findOrFail($id)
 * @method static $this firstOrCreate() firstOrCreate(array $array)
 * @method static $this firstOrNew() firstOrNew(array $array)
 * @method static Builder orderBy() orderBy(string $column, string $direction = 'asc')
 * @method static Builder where() where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static Builder whereBetween() whereBetween($column, array $values, $boolean = 'and', $not = false)
 * @method static Builder whereIn() whereIn($column, $values, $boolean = 'and', $not = false)
 *
 * @package FrenchFrogs\Laravel\Database\Eloquent
 */
class Model extends \Illuminate\Database\Eloquent\Model
{

    const BINARY16_UUID = 'binuuid';
    const NENUPHAR = 'nenuphar';


    /**
     *
     * Validation du model
     *
     */
    public function validate()
    {

        // chargement de la la classe
        $maker = Maker::load(static::class);

        // Initialisation des information de generation
        $rules = [];
        $messages = [];

        // On recherche les informations dans les annotations
        foreach($maker->getTags() as $tag) {

            // si pas au moins 2 element, on zap
            if (!is_array($tag) || count($tag) != 2) continue;

            // Verification que le tag est bien un validate
            $type = array_shift($tag);
            if ($type != 'validate') continue;

            // Extraction des informations
            $result = PCRE::fromPattern('#^(?<property>[^\s]+) (?<validator>.+)#')->match(current($tag));

            if ($result->isNotEmpty()) {

                // Recuperation du nom du champs
                $property = $result->get('property');

                foreach (explode('|', $result->get('validator')) as $validator) {

                    $v = PCRE::fromPattern('#^(?<rule>[^`]+)(`(?<message>.+)`)?$#')->match($validator);

                    // Inscription des regles pour la propriété
                    empty($rules[$property]) && $rules[$property] = [];
                    $rules[$property][] = $v->get('rule');

                    // Si message, inscription du message
                    if ($v->has('message')) {
                        $r = PCRE::fromPattern('#^(?<validator>[^:]+)(:.+)?$#')->match($v->get('rule'));
                        $messages[$property . '.' .$r->get('validator')] = $v->get('message');
                    }
                }
            }
        }

        return \Validator::make($this->toArray(), $rules, $messages);
    }

    /**
     * Desactivate gard
     *
     * @var bool
     */
    protected static $unguarded = true;


    /**
     * Cast an attribute to a native PHP type.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function castAttribute($key, $value)
    {
        if (is_null($value)) {
            return $value;
        }

        switch ($this->getCastType($key)) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'object':
                return $this->fromJson($value, true);
            case 'array':
            case 'json':
                return $this->fromJson($value);
            case 'collection':
                return new BaseCollection($this->fromJson($value));
            case 'date':
                return $this->asDate($value);
            case 'datetime':
                return $this->asDateTime($value);
            case 'timestamp':
                return $this->asTimestamp($value);



            case static::BINARY16_UUID:
                return \Webpatser\Uuid\Uuid::import($value)->bytes;
            case static::NENUPHAR :
                return '';
            default:
                return $value;
        }
    }


    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        // First we will check for the presence of a mutator for the set operation
        // which simply lets the developers tweak the attribute as it is set on
        // the model, such as "json_encoding" an listing of data for storage.
        if ($this->hasSetMutator($key)) {
            $method = 'set'.Str::studly($key).'Attribute';

            return $this->{$method}($value);
        }

        // si on passe un model, on recupere l'id du model
        if ($value instanceof Model) {
            $value = $value->getKey();
        }

        // If an attribute is listed as a "date", we'll convert it from a DateTime
        // instance into a form proper for storage on the database tables using
        // the connection grammar's date format. We will auto set the values.
        elseif ($value && $this->isDateAttribute($key)) {
            $value = $this->fromDateTime($value);
        }

        if ($this->isJsonCastable($key) && ! is_null($value)) {
            $value = $this->castAttributeAsJson($key, $value);
        }

        // Gestion des uuid primaire
        if ($this->hasCast($key, static::BINARY16_UUID) && ! is_null($value)) {
            $value = $this->castAsBinaryBytes($value);
        }

        // Gestion des nenuphars
        if ($this->hasCast($key, static::NENUPHAR) && ! is_null($value)) {
            $value = $this->castAsNenuphar($value);
        }

        // If this attribute contains a JSON ->, we'll set the proper value in the
        // attribute's underlying array. This takes care of properly nesting an
        // attribute in the array's value in the case of deeply nested items.
        if (Str::contains($key, '->')) {
            return $this->fillJsonAttribute($key, $value);
        }

        $this->attributes[$key] = $value;

        return $this;
    }


    /**
     * cast a filed as a nenuphar
     *
     * @param $value
     * @return mixed
     */
    public function castAsNenuphar($value)
    {

        return $value;
    }

    /**
     * Cast binary
     *
     * @param $value
     * @return string
     */
    public function castAsBinaryBytes($value) {

        // si on n'a pas de uuid, on le force
        if (!($value instanceof Uuid)) {
            $value = Uuid::import($value);
        }

        return $value->bytes;
    }

    /**
     * Handle dynamic method calls into the model.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (in_array($method, ['increment', 'decrement'])) {
            return $this->$method(...$parameters);
        }


        if ($this->getKeyType() == static::BINARY16_UUID) {
            if ($method == 'find') {
                $parameters[0] = $this->castAsBinaryBytes($parameters[0]);
            }
        }

        return $this->newQuery()->$method(...$parameters);
    }


    /**
     * Insert the given attributes and set the ID on the model.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array  $attributes
     * @return void
     */
    protected function insertAndSetId(Builder $query, $attributes)
    {
        $keyName = $this->getKeyName();

        // uuid management
        if ($this->getKeyType() == static::BINARY16_UUID) {

            $id = \Webpatser\Uuid\Uuid::generate(4);
            $attributes[$keyName] = $id->bytes;
            $query->insert($attributes);

        // auto increment
        } else {
            $id = $query->insertGetId($attributes,$keyName);
        }

        $this->setAttribute($keyName, $id);
    }
}