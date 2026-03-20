<?php

declare (strict_types=1);
namespace Drupal\Core\Batch;

use Drupal\Core\Queue\Queue_Interface;
use Drupal\Core\String_Translation\Translatable_Markup;
/**
 * Builds an array for a batch process.
 *
 * Example code to create a batch:
 * @code
 * $batch_builder = (new BatchBuilder())
 *   ->setTitle(t('Batch Title'))
 *   ->setFinishCallback('batch_example_finished_callback')
 *   ->setInitMessage(t('The initialization message (optional)'));
 * foreach ($ids as $id) {
 *   $batch_builder->addOperation('batch_example_callback', [$id]);
 * }
 * batch_set($batch_builder->toArray());
 * @endcode
 *
 * To prevent duplicate batches from being created inadvertently in the same
 * page request, you can use ::isSetIdRegistered and ::registerSetId to check
 * and see if this batch has been built before.
 * @code
 * if (!BatchBuilder::isSetIdRegistered('my_unique_id')) {
 *   $batch_builder = (new BatchBuilder())
 *     ->registerSetId('my_unique_id')
 *     ->setTitle(t('Batch Title'))
 *     ->setFinishCallback('batch_example_finished_callback')
 *     ->setInitMessage(t('The initialization message (optional)'));
 *   foreach ($ids as $id) {
 *     $batch_builder->addOperation('batch_example_callback', [$id]);
 *   }
 *   batch_set($batch_builder->toArray());
 * }
 * @endcode
 */
class Batch_Builder
{
    /**
     * The set of operations to be processed.
     *
     * Each operation is a tuple of the function / method to use and an array
     * containing any parameters to be passed.
     *
     * @var array
     */
    protected $operations = [];
    /**
     * The title for the batch.
     *
     * @var string|\Drupal\Core\StringTranslation\TranslatableMarkup
     */
    protected \Drupal\Core\String_Translation\Translatable_Markup $title;
    /**
     * The initializing message for the batch.
     *
     * @var string|\Drupal\Core\StringTranslation\TranslatableMarkup
     */
    protected \Drupal\Core\String_Translation\Translatable_Markup $init_message;
    /**
     * The message to be shown while the batch is in progress.
     *
     * @var string|\Drupal\Core\StringTranslation\TranslatableMarkup
     */
    protected \Drupal\Core\String_Translation\Translatable_Markup $progress_message;
    /**
     * The message to be shown if a problem occurs.
     *
     * @var string|\Drupal\Core\StringTranslation\TranslatableMarkup
     */
    protected \Drupal\Core\String_Translation\Translatable_Markup $error_message;
    /**
     * The name of a function / method to be called when the batch finishes.
     *
     * @var string
     */
    protected $finished;
    /**
     * The file containing the operation and finished callbacks.
     *
     * If the callbacks are in the .module file or can be autoloaded, for example,
     * static methods on a class, then this does not need to be set.
     *
     * @var string
     */
    protected $file;
    /**
     * An array of libraries to be included when processing the batch.
     *
     * @var string[]
     */
    protected $libraries = [];
    /**
     * An array of options to be used with the redirect URL.
     *
     * @var array
     */
    protected $url_options = [];
    /**
     * Specifies if the batch is progressive.
     *
     * If true, multiple calls are used. Otherwise an attempt is made to process
     * the batch in a single run.
     *
     * @var bool
     */
    protected $progressive = true;
    /**
     * The details of the queue to use.
     *
     * A tuple containing the name of the queue and the class of the queue to use.
     *
     * @var array
     */
    protected $queue;
    /**
     * A static array of custom batch ids.
     *
     * @var string[]
     */
    protected static array $registered_set_ids = [];
    /**
     * Sets the default values for the batch builder.
     */
    public function __construct()
    {
        $this->title = new Translatable_Markup('Processing');
        $this->init_message = new Translatable_Markup('Initializing.');
        $this->progress_message = new Translatable_Markup('Completed @current of @total.');
        $this->error_message = new Translatable_Markup('An error has occurred.');
    }
    /**
     * Sets the title.
     *
     * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $title
     *   The title.
     *
     * @return $this
     */
    public function set_title($title): static
    {
        $this->title = $title;
        return $this;
    }
    /**
     * Sets the finished callback.
     *
     * This callback will be executed if the batch process is done.
     *
     * @param callable $callback
     *   The callback.
     *
     * @return $this
     */
    public function set_finish_callback(callable $callback): static
    {
        $this->finished = $callback;
        return $this;
    }
    /**
     * Sets the displayed message while processing is initialized.
     *
     * Defaults to 'Initializing.'.
     *
     * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $message
     *   The text to display.
     *
     * @return $this
     */
    public function set_init_message($message): static
    {
        $this->init_message = $message;
        return $this;
    }
    /**
     * Sets the message to display when the batch is being processed.
     *
     * Defaults to 'Completed @current of @total.'.
     *
     * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $message
     *   The text to display.  Available placeholders are:
     *   - '@current'
     *   - '@remaining'
     *   - '@total'
     *   - '@percentage'
     *   - '@estimate'
     *   - '@elapsed'.
     *
     * @return $this
     */
    public function set_progress_message($message): static
    {
        $this->progress_message = $message;
        return $this;
    }
    /**
     * Sets the message to display if an error occurs while processing.
     *
     * Defaults to 'An error has occurred.'.
     *
     * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $message
     *   The text to display.
     *
     * @return $this
     */
    public function set_error_message($message): static
    {
        $this->error_message = $message;
        return $this;
    }
    /**
     * Sets the file that contains the callback functions.
     *
     * The path should be relative to base_path(), and thus should be built using
     * \Drupal\Core\Extension\ExtensionList::getPath(). Defaults to
     * {module_name}.module.
     *
     * The file needs to be set before using ::addOperation(),
     * ::setFinishCallback(), or any other function that uses callbacks from the
     * file. This is so that PHP knows about the included functions.
     *
     * @param string $filename
     *   The path to the file.
     *
     * @return $this
     */
    public function set_file($filename): static
    {
        include_once $filename;
        $this->file = $filename;
        return $this;
    }
    /**
     * Sets the libraries to use when processing the batch.
     *
     * Adds the libraries for use on the progress page. Any previously added
     * libraries are removed.
     *
     * @param string[] $libraries
     *   The libraries to be used.
     *
     * @return $this
     */
    public function set_libraries(array $libraries): static
    {
        $this->libraries = $libraries;
        return $this;
    }
    /**
     * Sets the options for redirect URLs.
     *
     * @param array $options
     *   The options to use.
     *
     * @return $this
     *
     * @see \Drupal\Core\Url
     */
    public function set_url_options(array $options): static
    {
        $this->url_options = $options;
        return $this;
    }
    /**
     * Sets the batch to run progressively.
     *
     * @param bool $is_progressive
     *   (optional) A Boolean that indicates whether or not the batch needs to run
     *   progressively. TRUE indicates that the batch will run in more than one
     *   run. FALSE indicates that the batch will finish in a single run. Defaults
     *   to TRUE.
     *
     * @return $this
     */
    public function set_progressive($is_progressive = true): static
    {
        $this->progressive = $is_progressive;
        return $this;
    }
    /**
     * Sets an override for the default queue.
     *
     * The class will typically either be \Drupal\Core\Queue\Batch or
     * \Drupal\Core\Queue\BatchMemory. The class defaults to Batch if progressive
     * is TRUE, or to BatchMemory if progressive is FALSE.
     *
     * @param string $name
     *   The unique identifier for the queue.
     * @param string $class
     *   The fully qualified name of a class that implements
     *   \Drupal\Core\Queue\QueueInterface.
     *
     * @return $this
     */
    public function set_queue($name, $class): static
    {
        if (!class_exists($class)) {
            throw new \InvalidArgumentException('Class ' . $class . ' does not exist.');
        }
        if (!in_array(Queue_Interface::class, class_implements($class))) {
            throw new \InvalidArgumentException('Class ' . $class . ' does not implement \Drupal\Core\Queue\QueueInterface.');
        }
        $this->queue = ['name' => $name, 'class' => $class];
        return $this;
    }
    /**
     * Adds a batch operation.
     *
     * @param callable $callback
     *   The name of the callback function.
     * @param array $arguments
     *   An array of arguments to pass to the callback function.
     *
     * @return $this
     */
    public function add_operation(callable $callback, array $arguments = []): static
    {
        $this->operations[] = [$callback, $arguments];
        return $this;
    }
    /**
     * Checks if a set ID has been registered during this request.
     *
     * @param string $setId
     *   The set ID to check.
     *
     * @return bool
     *   True if this set ID has been registered.
     */
    public static function is_set_id_registered(string $set_id): bool
    {
        return isset(static::$registered_set_ids[$set_id]);
    }
    /**
     * Registers a set ID for this batch.
     *
     * @param string $setId
     *   The set ID to register.
     *
     * @return $this
     */
    public function register_set_id(string $set_id): self
    {
        static::$registered_set_ids[$set_id] = true;
        return $this;
    }
    /**
     * Converts a \Drupal\Core\Batch\Batch object into an array.
     *
     * @return array
     *   The array representation of the object.
     */
    public function to_array(): array
    {
        $array = ['operations' => $this->operations ?: [], 'title' => $this->title ?: '', 'init_message' => $this->init_message ?: '', 'progress_message' => $this->progress_message ?: '', 'error_message' => $this->error_message ?: '', 'finished' => $this->finished, 'file' => $this->file, 'library' => $this->libraries ?: [], 'url_options' => $this->url_options ?: [], 'progressive' => $this->progressive];
        if ($this->queue) {
            $array['queue'] = $this->queue;
        }
        return $array;
    }
}