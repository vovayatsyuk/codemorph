<?php

require "ExcludedFilesIterator.php";

class CodeMorph
{
    /**
     * @var array
     */
    protected $_patterns = array(
        'placeholder' => '{{morph}}',
        'replacement' => array(
            // {{demo}}:{{$foo = 'bar';}}
            'singleline' => '/^(\s*).*{{(\w+)}}:{{(.*?)}}/',
            'multiline'  => array(
                // {{demo}}:{{
                'start'     => '/^(\s*).*{{(\w+)}}:{{/',
                // }}
                'end'       => '/}}/',
                // $foo = 'bar'; inside or outside comment tags
                'content'   => '/^(\s*)(\/\/|<!--|#|\/\*)?\s?(.*)$/'
            )
        )
    );

    /**
     * @var array
     */
    protected $_pairCommentTags = array(
        '/*'   => '*/',
        '<!--' => '-->'
    );

    /**
     * Path to the source directory
     *
     * @var string
     */
    protected $_sourcePath;

    /**
     * Path to the destination directiry
     *
     * @var string
     */
    protected $_destinationPath;

    /**
     * Version to generate
     *
     * @var string
     */
    protected $_mode;

    /**
     * Optional configuration
     *
     * @var array
     */
    protected $_config;

    /**
     * Log file handle
     *
     * @var resource
     */
    protected $_logHandle;

    /**
     * @param string $path
     */
    public function setSourcePath($path)
    {
        $this->_sourcePath = rtrim($path, '/\\');
        return $this;
    }

    /**
     * @param string $path
     */
    public function setDestinationPath($path)
    {
        $this->_destinationPath = rtrim($path, '/\\');
        return $this;
    }

    /**
     * @param string $mode
     */
    public function setMode($mode)
    {
        $this->_mode = $mode;
        return $this;
    }

    /**
     * Run the script
     *
     * @return void
     */
    public function run()
    {
        $this->_loadConfiguration();

        try {
            $di = new RecursiveIteratorIterator(
                new ExcludedFilesIterator(
                    new RecursiveDirectoryIterator(
                        $this->_sourcePath,
                        RecursiveDirectoryIterator::SKIP_DOTS
                    ),
                    (empty($this->_config['iterator']['exclude']) ?
                        array() : $this->_config['iterator']['exclude'])
                ),
                RecursiveIteratorIterator::SELF_FIRST
            );
        } catch (Exception $e) {
            echo "Unable to open directory '{$this->_sourcePath}'\n";
            exit;
        }

        $count   = 0;
        $success = 0;
        $failure = 0;
        foreach ($di as $item) {
            if (!$item->isFile()) {
                continue;
            }
            $relativePath = str_replace($this->_sourcePath, '', $item->getPathname());
            $relativePath = str_replace('\\', '/', $relativePath);
            $relativePath = trim($relativePath, '/\\');
            if (!$this->_isRegionFile($relativePath)) {
                continue;
            }

            $destination  = implode('/', array(
                $this->_destinationPath,
                $this->_mode,
                $relativePath
            ));
            $pathinfo = pathinfo($destination);

            if (!file_exists($pathinfo['dirname'])) {
                @mkdir($pathinfo['dirname'], 0777, true);

                if (!file_exists($pathinfo['dirname'])) {
                    echo "Unable to create '{$pathinfo['dirname']}'\n";
                    exit;
                }
            }

            $count++;
            $this->_log($destination);
            if ($this->_processFile($item->getPathname(), $destination)) {
                $success++;
            } else {
                $failure++;
            }
        }

        echo sprintf(
            "%s: %d files processed. Success: %d, failure: %d. See the %s for more details\n",
            $this->_mode,
            $count,
            $success,
            $failure,
            $this->_getLogFilePath()
        );
    }

    /**
     * Process the file contents
     *
     * @param  string  $source       File path
     * @param  string  $destination  File path
     * @return void
     */
    protected function _processFile($source, $destination)
    {
        $sourceHandle = @fopen($source, "r");
        $destHandle   = @fopen($destination, "w");
        if (!$sourceHandle) {
            $this->_log("Cannot open file: " . $source);
            return false;
        }
        if (!$destHandle) {
            $this->_log("Cannot open file: " . $destination);
            return false;
        }

        $mode = '';
        $replacement    = '';
        $originalCode   = '';
        $useReplacement = false;
        $insideMorphRule = false;
        $insideMultilineRule = false;
        while (($line = fgets($sourceHandle)) !== false) {
            if ($insideMultilineRule && $this->_mode != $mode) {
                if (preg_match($this->_patterns['replacement']['multiline']['end'], $line)) {
                    $insideMultilineRule = false;
                }
                continue;
            }

            // check for opening or closing morph tag
            if (false !== strpos($line, $this->_patterns['placeholder'])) {
                if ($insideMorphRule) {
                    if ($useReplacement) {
                        $this->_log("Original:    " . $originalCode);
                        $this->_log("Replacement: " . $replacement);
                    }
                    fwrite($destHandle, $useReplacement ? $replacement : $originalCode);
                    $replacement    = '';
                    $originalCode   = '';
                    $useReplacement = false;
                    $insideMorphRule = false;
                    $insideMultilineRule = false;
                } else {
                    $insideMorphRule = true;
                }
                continue;
            }

            if (!$insideMorphRule) {
                fwrite($destHandle, $line);
                continue;
            }

            if (!$insideMultilineRule) {
                preg_match($this->_patterns['replacement']['singleline'], $line, $matches);
                if ($matches) {
                    list(, $spaces, $mode, $code) = $matches;
                    if ($matches && $this->_mode == $mode) {
                        $useReplacement = true;
                        if (!empty($code)) {
                            $replacement .=  $spaces . $code . "\n";
                        }
                    }
                } else {
                    preg_match(
                        $this->_patterns['replacement']['multiline']['start'],
                        $line,
                        $matches
                    );
                    if ($matches) {
                        $mode = $matches[2];
                        $insideMultilineRule = true;
                    } else {
                        // Collect original code. We will use it in case if no rule
                        // for current mode will be found
                        // Original code is the code that does not match the
                        // replacement patterns and outside of multiline rule
                        $originalCode .= $line;
                    }
                }
            } else {
                // We are inside multiline rule and $mode == $this->_mode
                if (preg_match($this->_patterns['replacement']['multiline']['end'], $line)) {
                    $useReplacement = true;
                    $insideMultilineRule = false;
                } else {
                    preg_match(
                        $this->_patterns['replacement']['multiline']['content'],
                        $line,
                        $matches
                    );
                    if ($matches) {
                        $useReplacement = true;
                        list(, $spaces, $commentStart, $code) = $matches;
                        if (in_array($commentStart, array_keys($this->_pairCommentTags))) {
                            $code = str_replace($this->_pairCommentTags, '', $code);
                        }
                        $replacement .=  $spaces . $code . "\n";
                    }
                }
            }
        }

        if (!feof($sourceHandle)) {
            $this->_log("Error: unexpected fgets() fail");
            return false;
        }
        fclose($sourceHandle);
        fclose($destHandle);

        return true;
    }

    protected function _log($message)
    {
        if (null === $this->_logHandle) {
            $this->_logHandle = @fopen($this->_getLogFilePath(), 'w');
        }
        fwrite($this->_logHandle, $message . "\n");
    }

    protected function _getLogFilePath()
    {
        return $this->_destinationPath . '/log.txt';
    }

    /**
     * Load optional config file from source directory
     *
     * @return void
     */
    protected function _loadConfiguration()
    {
        $filename = $this->_sourcePath . '/morph.ini';
        if (is_readable($filename)) {
            $this->_config = parse_ini_file($filename, true);
        } else {
            $this->_config = array(
                'morph_files' => array(
                    'include' => array('*')
                )
            );
        }
    }

    /**
     * Check is the file should be morphed
     *
     * @param  string  $relativePath Path to the file, relative to source dir
     * @return boolean
     */
    protected function _isRegionFile($relativePath)
    {
        if (!empty($this->_config['morph_files']['include'])) {
            $included = $this->_config['morph_files']['include'];
            if (in_array($relativePath, $included)) {
                return true;
            }
        }

        if (!empty($this->_config['morph_files']['exclude'])) {
            $excluded = $this->_config['morph_files']['exclude'];
            if (in_array($relativePath, $excluded) || in_array('*', $excluded)) {
                return false;
            }
        }

        $content = file_get_contents($this->_sourcePath . '/' . $relativePath);
        if (!$content || false === strpos($content, $this->_patterns['placeholder'])) {
            return false;
        }

        return true;
    }
}
