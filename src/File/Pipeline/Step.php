<?php

/* this file is part of pipelines */

namespace Ktomk\Pipelines\File\Pipeline;

use Ktomk\Pipelines\File\Artifacts;
use Ktomk\Pipelines\File\Dom\FileNode;
use Ktomk\Pipelines\File\Image;
use Ktomk\Pipelines\File\ParseException;
use Ktomk\Pipelines\File\Pipeline;

class Step implements FileNode
{
    /**
     * @var array
     */
    private $step;

    /**
     * @var int number of the step, starting at one
     */
    private $index;

    /**
     * @var Pipeline
     */
    private $pipeline;

    /**
     * @var array step environment variables
     *   BITBUCKET_PARALLEL_STEP - zero-based index of the current step in the group, e.g. 0, 1, 2, ...
     *   BITBUCKET_PARALLEL_STEP_COUNT - total number of steps in the group, e.g. 5.
     */
    private $env;

    /**
     * Step constructor.
     *
     * @param Pipeline $pipeline
     * @param int $index
     * @param array $step
     * @param array $env [optional] environment variables in array notation for the new step
     */
    public function __construct(Pipeline $pipeline, $index, array $step, array $env = array())
    {
        // quick validation: image name
        Image::validate($step);

        // quick validation: trigger
        $this->validateTrigger($step, (bool)$env);

        // quick validation: script + after-script
        $this->parseScript($step);
        $this->parseAfterScript($step);

        $this->pipeline = $pipeline;
        $this->index = $index;
        $this->step = $step;
        $this->env = $env;
    }

    /**
     * @throws ParseException
     *
     * @return null|Artifacts
     */
    public function getArtifacts()
    {
        return isset($this->step['artifacts'])
            ? new Artifacts($this->step['artifacts'])
            : null;
    }

    /**
     * @throws ParseException
     *
     * @return Image
     */
    public function getImage()
    {
        return isset($this->step['image'])
            ? new Image($this->step['image'])
            : $this->pipeline->getFile()->getImage();
    }

    /**
     * @return null|string
     */
    public function getName()
    {
        return isset($this->step['name'])
            ? (string)$this->step['name']
            : null;
    }

    /**
     * @return StepServices
     */
    public function getServices()
    {
        $services = isset($this->step['services']) ? $this->step['services'] : array();

        return new StepServices($this, $services);
    }

    /**
     * @return array|string[]
     */
    public function getScript()
    {
        return $this->step['script'];
    }

    /**
     * @return array|string[]
     */
    public function getAfterScript()
    {
        if (isset($this->step['after-script'])) {
            return $this->step['after-script'];
        }

        return array();
    }

    /**
     * @return bool
     */
    public function isManual()
    {
        if (0 === $this->index) {
            return false;
        }

        return (isset($this->step['trigger']) && 'manual' === $this->step['trigger']);
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return array
     */
    public function jsonSerialize()
    {
        $image = $this->getImage();
        $image = null === $image ? '' : $image->jsonSerialize();

        return array(
            'name' => $this->getName(),
            'image' => $image,
            'script' => $this->getScript(),
            'artifacts' => $this->getArtifacts(),
        );
    }

    /**
     * @return int
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * @return Pipeline
     * @codeCoverageIgnore
     */
    public function getPipeline()
    {
        return $this->pipeline;
    }

    /**
     * @return array step container environment variables (e.g. parallel a step)
     */
    public function getEnv()
    {
        return $this->env;
    }

    /**
     * @return \Ktomk\Pipelines\File\File
     */
    public function getFile()
    {
        return $this->pipeline->getFile();
    }

    /**
     * validate step trigger (none, manual, automatic)
     *
     * @param array $array
     * @param bool $isParallelStep
     *
     * @return void
     */
    private function validateTrigger(array $array, $isParallelStep)
    {
        if (!array_key_exists('trigger', $array)) {
            return;
        }

        $trigger = $array['trigger'];
        if ($isParallelStep) {
            throw new ParseException("Unexpected property 'trigger' in parallel step");
        }

        if (!in_array($trigger, array('manual', 'automatic'), true)) {
            throw new ParseException("'trigger' expects either 'manual' or 'automatic'");
        }
    }

    /**
     * Parse a step script section
     *
     * @param array $step
     *
     * @throws ParseException
     *
     * @return void
     */
    private function parseScript(array $step)
    {
        if (!isset($step['script'])) {
            throw new ParseException("'step' requires a script");
        }
        $this->parseNamedScript('script', $step);
    }

    /**
     * @param array $step
     *
     * @return void
     */
    private function parseAfterScript(array $step)
    {
        if (isset($step['after-script'])) {
            $this->parseNamedScript('after-script', $step);
        }
    }

    /**
     * @param string $name
     * @param $script
     *
     * @return void
     */
    private function parseNamedScript($name, array $script)
    {
        if (!is_array($script[$name]) || !count($script[$name])) {
            throw new ParseException("'${name}' requires a list of commands");
        }

        foreach ($script[$name] as $index => $line) {
            $this->parseNamedScriptLine($name, $index, $line);
        }
    }

    /**
     * @param string $name
     * @param int $index
     * @param null|array|bool|float|int|string $line
     *
     * @return void
     */
    private function parseNamedScriptLine($name, $index, $line)
    {
        $standard = is_scalar($line) || null === $line;
        $pipe = is_array($line) && isset($line['pipe']) && is_string($line['pipe']);

        if (!($standard || ('script' === $name && $pipe))) {
            throw new ParseException(sprintf(
                "'%s' requires a list of commands, step #%d is not a command",
                $name,
                $index
            ));
        }
    }
}
