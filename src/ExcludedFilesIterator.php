<?php

class ExcludedFilesIterator extends RecursiveFilterIterator
{
    protected $_excludedFileNames = array(
        '.git',
        '.gitignore',
        '.gitmodules',
        '.svn',
        'composer.json',
        'composer.lock',
        'gulpfile.js',
        'modman',
        'morph.ini',
        'node_modules'
    );

    public function __construct(RecursiveIterator $recursiveIter, $excludedFolders = array())
    {
        if ($excludedFolders) {
            $this->_excludedFileNames = array_merge(
                $this->_excludedFileNames,
                $excludedFolders
            );
        }
        parent::__construct($recursiveIter);
    }

    public function accept()
    {
        return !in_array($this->current()->getFilename(), $this->_excludedFileNames);
    }
}
