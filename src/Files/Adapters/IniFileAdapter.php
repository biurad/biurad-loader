<?php

declare(strict_types=1);

/*
 * This code is under BSD 3-Clause "New" or "Revised" License.
 *
 * PHP version 7 and above required
 *
 * @category  LoaderManager
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * @link      https://www.biurad.com/projects/biurad-loader
 * @since     Version 0.1
 */

namespace BiuradPHP\Loader\Files\Adapters;

use RuntimeException;

/**
 * Reading and generating Ini files.
 *
 * @author Zend Technologies USA Inc <http://www.zend.com>
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 * @license BSD-3-Clause
 */
final class IniFileAdapter extends AbstractAdapter
{
    /**
     * Separator for nesting levels of configuration data identifiers.
     *
     * @var string
     */
    protected $nestSeparator = '.';

    /**
     * Flag which determines whether sections are processed or not.
     *
     * @see https://www.php.net/parse_ini_file
     *
     * @var bool
     */
    protected $processSections = true;

    /**
     * If true the INI string is rendered in the global namespace without
     * sections.
     *
     * @var bool
     */
    protected $renderWithoutSections = false;

    /**
     * {@inheritdoc}
     */
    public function supports(string $file): bool
    {
        return 'ini' === strtolower(pathinfo($file, PATHINFO_EXTENSION));
    }

    /**
     * Reads configuration from INI data.
     *
     * @param string $string
     *
     * @return array|bool
     *
     * @throws RuntimeException
     */
    protected function processFrom(string $string): array
    {
        $ini = parse_ini_string($string, $this->getProcessSections());
        restore_error_handler();

        return $this->process($ini);
    }

    /**
     * Generates configuration in INI format.
     * @param array $data
     * @return string
     */
    protected function processDump(array $data): string
    {
        $class = __CLASS__;

        return "; generated by $class\n\n".$this->processConfig($data);
    }

    /**
     * Set nest separator.
     *
     * @param string $separator
     *
     * @return self
     */
    public function setNestSeparator($separator)
    {
        $this->nestSeparator = $separator;

        return $this;
    }

    /**
     * Get nest separator.
     *
     * @return string
     */
    public function getNestSeparator()
    {
        return $this->nestSeparator;
    }

    /**
     * Marks whether sections should be processed.
     * When sections are not processed,section names are stripped and section
     * values are merged.
     *
     * @see https://www.php.net/parse_ini_file
     *
     * @param bool $processSections
     *
     * @return $this
     */
    public function setProcessSections($processSections)
    {
        $this->processSections = (bool) $processSections;

        return $this;
    }

    /**
     * Get if sections should be processed
     * When sections are not processed,section names are stripped and section
     * values are merged.
     *
     * @see https://www.php.net/parse_ini_file
     *
     * @return bool
     */
    public function getProcessSections()
    {
        return $this->processSections;
    }

    /**
     * Set if rendering should occur without sections or not.
     *
     * If set to true, the INI file is rendered without sections completely
     * into the global namespace of the INI file.
     *
     * @param bool $withoutSections
     *
     * @return self
     */
    public function setRenderWithoutSectionsFlags($withoutSections)
    {
        $this->renderWithoutSections = (bool) $withoutSections;

        return $this;
    }

    /**
     * Return whether the writer should render without sections.
     *
     * @return bool
     */
    public function shouldRenderWithoutSections()
    {
        return $this->renderWithoutSections;
    }

    /**
     * Process data from the parsed ini file.
     *
     * @param array $data
     *
     * @return array
     */
    protected function process(array $data)
    {
        $config = [];

        foreach ($data as $section => $value) {
            if (is_array($value)) {
                if (strpos($section, $this->nestSeparator) !== false) {
                    $sections = explode($this->nestSeparator, $section);
                    $config = array_merge_recursive($config, $this->buildNestedSection($sections, $value));
                } else {
                    $config[$section] = $this->processSection($value);
                }
            } else {
                $this->processKey($section, $value, $config);
            }
        }

        return $config;
    }

    /**
     * Process a nested section.
     *
     * @param array $sections
     * @param mixed $value
     *
     * @return array
     */
    private function buildNestedSection($sections, $value)
    {
        if (!$sections) {
            return $this->processSection($value);
        }

        $nestedSection = [];

        $first = array_shift($sections);
        $nestedSection[$first] = $this->buildNestedSection($sections, $value);

        return $nestedSection;
    }

    /**
     * Process a section.
     *
     * @param array $section
     *
     * @return array
     */
    protected function processSection(array $section)
    {
        $config = [];

        foreach ($section as $key => $value) {
            $this->processKey($key, $value, $config);
        }

        return $config;
    }

    /**
     * Process a key.
     *
     * @param string $key
     * @param string $value
     * @param array $config
     *
     * @return void
     *
     */
    protected function processKey($key, $value, array &$config)
    {
        if (strpos($key, $this->nestSeparator) !== false) {
            $pieces = explode($this->nestSeparator, $key, 2);

            if ($pieces[0] === '' || $pieces[1] === '') {
                throw new RuntimeException(sprintf('Invalid key "%s"', $key));
            }

            if (!isset($config[$pieces[0]])) {
                if ($pieces[0] === '0' && !empty($config)) {
                    $config = [$pieces[0] => $config];
                } else {
                    $config[$pieces[0]] = [];
                }
            } elseif (!is_array($config[$pieces[0]])) {
                throw new RuntimeException(
                    sprintf('Cannot create sub-key for "%s", as key already exists', $pieces[0])
                );
            }

            $this->processKey($pieces[1], $value, $config[$pieces[0]]);
        } else {
            $config[$key] = $value;
        }
    }

    /**
     * Process array into ini.
     *
     * @param array $config
     *
     * @return string
     */
    protected function processConfig(array $config)
    {
        $iniString = '';

        if ($this->shouldRenderWithoutSections()) {
            $iniString .= $this->addBranch($config);
        } else {
            $config = $this->sortRootElements($config);

            foreach ($config as $sectionName => $data) {
                if (!is_array($data)) {
                    $iniString .= $sectionName.' = '.$this->prepareValue($data)."\n";
                } else {
                    $iniString .= '['.$sectionName.']'."\n".$this->addBranch($data)."\n";
                }
            }
        }

        return $iniString;
    }

    /**
     * Add a branch to an INI string recursively.
     *
     * @param array $config
     * @param array $parents
     *
     * @return string
     */
    protected function addBranch(array $config, $parents = [])
    {
        $iniString = '';

        foreach ($config as $key => $value) {
            $group = array_merge($parents, [$key]);

            if (is_array($value)) {
                $iniString .= $this->addBranch($value, $group);
            } else {
                $iniString .= implode($this->nestSeparator, $group)
                           .' = '
                           .$this->prepareValue($value)
                           ."\n";
            }
        }

        return $iniString;
    }

    /**
     * Prepare a value for INI.
     *
     * @param mixed $value
     *
     * @return string
     *
     * @throws RuntimeException
     */
    protected function prepareValue($value)
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (!isset($value) || false === strpos($value, '"')) {
            return '"'.$value.'"';
        }

        throw new RuntimeException('Value can not contain double quotes');
    }

    /**
     * Root elements that are not assigned to any section needs to be on the
     * top of config.
     *
     * @param array $config
     *
     * @return array
     */
    protected function sortRootElements(array $config)
    {
        $sections = [];

        // Remove sections from config array.
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $sections[$key] = $value;
                unset($config[$key]);
            }
        }

        // Read sections to the end.
        foreach ($sections as $key => $value) {
            $config[$key] = $value;
        }

        return $config;
    }
}