<?php

declare(strict_types=1);

/*
 * This file is part of BiuradPHP opensource projects.
 *
 * PHP version 7 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace BiuradPHP\Loader\Files\Adapters;

use BiuradPHP\Loader\Exceptions\FileLoadingException;
use Nette;

/**
 * Reading and generating PHP files.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 * @license BSD-3-Clause
 */
final class PhpFileAdapter extends AbstractAdapter
{
    /**
     * {@inheritdoc}
     */
    public function supports(string $file): bool
    {
        return 'php' === \strtolower(\pathinfo($file, \PATHINFO_EXTENSION));
    }

    /**
     * {@inheritdoc}
     */
    public function fromFile(string $filename): array
    {
        return (array) require $filename;
    }

    /**
     * {@inheritdoc}
     */
    public function fromString($string): array
    {
        throw new FileLoadingException(\sprintf('Error reading PHP %s, this is not supported', \gettype($string)));
    }

    /**
     * Not supported on php files.
     *
     * @param string $config
     *
     * @return array
     */
    protected function processFrom(string $config): array
    {
        //TODO this method will be implemented if php arrays are updated to support this method.
        return [];
    }

    /**
     * Generates configuration in PHP format.
     *
     * @param array $data
     *
     * @return string
     */
    protected function processDump(array $data): string
    {
        $class = __CLASS__;

        if (\class_exists(Nette\PhpGenerator\Helpers::class)) {
            $dump = Nette\PhpGenerator\Helpers::dump($data);
        } else {
            $dump =  $this->encodeArray($data);
        }

        return "<?php // generated by $class \n\nreturn " . $dump . ';';
    }

    /**
     * Method to get an array as an exported string.
     *
     * @param array $var   the array to get as a string
     * @param int   $level used internally to indent rows
     *
     * @return string
     */
    private function encodeArray(array $var, $level = 0)
    {
        $read = [];

        foreach ($var as $key => $value) {
            if (\is_array($value) || \is_object($value)) {
                $read[] = \var_export($key, true) . ' => ' . $this->encodeArray((array) $value, $level + 1);
            } else {
                $read[] = \var_export($key, true) . ' => ' . \var_export($value, true);
            }
        }

        $space = \str_repeat('    ', $level);

        return "[\n    {$space}" . \implode(",\n    {$space}", $read) . "\n{$space}]";
    }
}
