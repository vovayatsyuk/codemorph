CodeMorph
=========

Script that generates multiple versions on one file according to replacement rules inside of it.

### How to use
1. Prepare morph rules is source files
 ```php
 ...
 // {{morph}}
 // {{demo}}:{{'lifetime' => 3600}}
 // {{trial}}:{{'lifetime' => $this->getTrialLifetime()}}
 'lifetime' => $this->getLifetime()
 // {{morph}}
 ...
 ```

 ```xml
 ...
 <!-- {{morph}}
 {{demo}}:{{
 <comment>Demo Version</comment>
 }} -->
 <comment>Full Version</comment>
 <!-- {{morph}} -->
 ...
 ```

2. Run the command:
 ```
 php -f path/to/morph.php "/source/folder" "/destination/folder" demo,trial
 ```

### Morph rules
1. Code to morph, should be wrapped into {{morph}} placeholders:
 ```
{{morph}}
original code
{{morph}}
 ```

2. Specify replacement rules
 ```
{{morph}}
{{demo}}:{{demo code}}
{{trial}}:{{
trial code
}}
original code
{{morph}}
 ```

3. Supported comments around the morph rules: #, /*, //, <!--

### Speed
To speedup the file parsing, you may create the morph.ini file inside your project root
and specify the rules for CodeMorph.
```ini
# files to parse
[morph_files]
include[] = "path/to/file1.php"
include[] = "path/to/file2.xml"
exclude[] = "*"

# folders to exclude by iterator
[iterator]
exclude[] = "build"
exclude[] = "node_modules"
```
