<?php

namespace Knp\Snappy;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Base generator class for medias
 *
 * @author  Matthieu Bontemps <matthieu.bontemps@knplabs.com>
 * @author  Antoine Hérault <antoine.herault@knplabs.com>
 */
abstract class AbstractGenerator implements GeneratorInterface
{
    /**
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $filesystem;
    /**
     * @var string
     */
    protected $defaultExtension;
    /**
     * @var array
     */
    protected $options = array();

    /**
     * @var string
     */
    private $binary;

    /**
     * Constructor
     *
     * @param  string $binary
     * @param  array  $options
     */
    public function __construct($binary, array $options = array(), $filesystem = null)
    {
        $this->filesystem = new Filesystem();

        $this->configure();

        $this->binary = $binary ?: '/usr/local/bin/wkhtmltopdf';
        $this->setOptions($options);
    }

    /**
     * This method must configure the media options
     *
     * @see AbstractGenerator::setOptions()
     */
    abstract protected function configure();

    /**
     * Sets the default extension.
     * Useful when letting Snappy deal with file creation
     *
     * @param string $defaultExtension
     */
    public function setDefaultExtension($defaultExtension)
    {
        $this->defaultExtension = $defaultExtension;
    }

    /**
     * Sets an option. Be aware that option values are NOT validated and that
     * it is your responsibility to validate user inputs
     *
     * @param  string $name  The option to set
     * @param  mixed  $value The value (NULL to unset)
     *
     * @throws \InvalidArgumentException
     */
    public function setOption($name, $value)
    {
        if (!array_key_exists($name, $this->options)) {
            throw new \InvalidArgumentException(sprintf('The option \'%s\' does not exist.', $name));
        }

        $this->options[$name] = $value;
    }

    /**
     * Sets an array of options
     *
     * @param  array $options An associative array of options as name/value
     */
    public function setOptions(array $options)
    {
        foreach ($options as $name => $value) {
            $this->setOption($name, $value);
        }
    }

    /**
     * Returns all the options
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * {@inheritDoc}
     */
    public function generate($input, $output, array $options = array(), $overwrite = false)
    {
        if (null === $this->binary) {
            throw new \LogicException(
                'You must define a binary prior to conversion.'
            );
        }

        $this->prepareOutput($output, $overwrite);

        $command = $this->getCommand($input, $output, $options);

        $this->executeCommand($command);

        return $this->checkOutput($output, $command);
    }

    /**
     * {@inheritDoc}
     */
    public function generateFromHtml($html, $output, array $options = array(), $overwrite = false)
    {
        $filename = $this->createTemporaryFile($html, 'html');

        $this->generate($filename, $output, $options, $overwrite);

        $this->filesystem->unlink($filename);
    }

    /**
     * {@inheritDoc}
     */
    public function getOutput($input, array $options = array())
    {
        $filename = $this->createTemporaryFile(null, $this->defaultExtension);

        $result = $this->generate($input, $filename, $options);

        $this->filesystem->unlink($filename);

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getOutputFromHtml($html, array $options = array())
    {
        $filename = $this->createTemporaryFile($html, 'html');

        return $this->getOutput($filename, $options, 'html');
    }

    /**
     * Defines the binary
     *
     * @param  string $binary The path/name of the binary
     */
    public function setBinary($binary)
    {
        $this->binary = $binary;
    }

    /**
     * Returns the binary
     *
     * @return string
     */
    public function getBinary()
    {
        return $this->binary;
    }

    /**
     * Returns the command for the given input and output files
     *
     * @param  string $input   The input file
     * @param  string $output  The output file
     * @param  array  $options An optional array of options that will be used
     *                         only for this command
     *
     * @return string
     */
    public function getCommand($input, $output, array $options = array())
    {
        $options = $this->mergeOptions($options);

        return $this->buildCommand($this->binary, $input, $output, $options);
    }

    /**
     * Merges the given array of options to the instance options and returns
     * the result options array. It does NOT change the instance options.
     *
     * @param array $options
     *
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    protected function mergeOptions(array $options)
    {
        $mergedOptions = $this->options;

        foreach ($options as $name => $value) {
            if (!array_key_exists($name, $mergedOptions)) {
                throw new \InvalidArgumentException(sprintf('The option \'%s\' does not exist.', $name));
            }

            $mergedOptions[$name] = $value;
        }

        return $mergedOptions;
    }

    /**
     * Checks the specified output
     *
     * @param  string $output  The output filename
     * @param  string $command The generation command
     *
     * @return string
     *
     * @throws \RuntimeException if the output file generation failed
     */
    protected function checkOutput($output, $command)
    {
        // the output file must exist
        if (!$this->filesystem->exists($output)) {
            throw new \RuntimeException(sprintf(
                'The file \'%s\' was not created (command: %s).',
                $output, $command
            ));
        }

        // the output file must not be empty
        if (0 === filesize($output)) {
            throw new \RuntimeException(sprintf(
                'The file \'%s\' was created but is empty (command: %s).',
                $output, $command
            ));
        }

        return $output;
    }

    /**
     * Creates a temporary file.
     * The file is not created if the $content argument is null
     *
     * @param  string $content  Optional content for the temporary file
     * @param  string $extension An optional extension for the filename
     *
     * @return string The filename
     */
    protected function createTemporaryFile($content = null, $extension = null)
    {
        $filename = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . uniqid('knp_snappy', true);

        if (null !== $extension) {
            $filename .= '.'.$extension;
        }

        file_put_contents($filename, (string) $content);

        return $filename;
    }

    /**
     * Builds the command string
     *
     * @param  string $binary   The binary path/name
     * @param  string $input    Url or file location of the page to process
     * @param  string $output   File location to the image-to-be
     * @param  array  $options  An array of options
     *
     * @return string
     */
    protected function buildCommand($binary, $input, $output, array $options = array())
    {
        $command = $binary;

        foreach ($options as $key => $option) {
            if (null !== $option && false !== $option) {
                if (true === $option) {
                    $command .= ' --'.$key;
                } elseif (is_array($option)) {
                    foreach ($option as $k => $v) {
                        $command .= ' --'.$key.(is_string($k) ? ' '.escapeshellarg($k) : '').' '.escapeshellarg($v);
                    }
                } else {
                    $command .= ' --'.$key.' '.escapeshellarg($option);
                }
            }
        }

        $command .= ' '.escapeshellarg($input).' '.escapeshellarg($output);;

        return $command;
    }

    /**
     * Executes the given command via shell and returns the complete output as
     * a string
     *
     * @param string $command
     *
     * @throws \RuntimeException
     */
    protected function executeCommand($command)
    {
        $process = new Process($command);
        $process->setTimeout(600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf(
                'The exit status code "%s" says something went wrong:'."\n"
                    .'stderr: "%s"'."\n"
                    .'stdout: "%s"'."\n"
                    .'command: %s.',
                $process->getExitCode(), $process->getOutput(), $process->getErrorOutput(), $command
            ));
        }
    }

    /**
     * Prepares the specified output
     *
     * @param  string  $filename  The output filename
     * @param  boolean $overwrite Whether to overwrite the file if it already
     *                            exist
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function prepareOutput($filename, $overwrite)
    {
        $directory = dirname($filename);

        if ($this->filesystem->exists($filename)) {
            $file = new \SplFileInfo($filename);
            if (!$file->isFile($filename)) {
                throw new \InvalidArgumentException(sprintf(
                    'The output file "%s" already exists and it is a %s.',
                    $filename, $file->isDir() ? 'directory' : 'link'
                ));
            }

            if (!$overwrite) {
                throw new \InvalidArgumentException(sprintf(
                    'The output file "%s" already exists.',
                    $filename
                ));
            }

            if ($overwrite && !$this->filesystem->unlink($filename)) {
                throw new \RuntimeException(sprintf(
                    'Could not delete already existing output file "%s".',
                    $filename
                ));
            }
        } elseif (!is_dir($filename) && !$this->filesystem->mkdir($directory)) {
            throw new \RuntimeException(sprintf(
                'The output file\'s directory "%s" could not be created.',
                $directory
            ));
        }
    }
}
