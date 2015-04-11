<?php

App::uses('Configure', 'Core');
App::uses('File', 'Utility');
App::uses('Folder', 'Utility');
App::uses('String', 'Utility');
App::uses('AppShell', 'Console/Command');

/**
 * Class ProjectShell
 * 
 * @author OanhNN <oanhnn@rikkeisoft.com>
 * @version 1.0.0
 * 
 * @property ProjectTask $Project
 */
class ProjectShell extends AppShell
{

    /**
     * Contains tasks to load and instantiate
     *
     * @var array
     */
    public $tasks = array('Project');

    /**
     * Root path
     * 
     * @var string
     */
    protected $_root;

    /**
     * Skeleton template path
     * 
     * @var string
     */
    protected $_skel;

    /**
     * Displays a header for the shell
     *
     * @return void
     */
    protected function _welcome()
    {
        $this->out();
        $this->out(__d('cake_console', '<info>Welcome to CakePHP %s Console</info>', 'v' . Configure::version()));
        $this->out(__d('cake_console', '<info>Customize by RikkeiSoft Co. Ltd.,</info>'));
        $this->hr();
    }

    /**
     * Assign $this->connection to the active task if a connection param is set.
     *
     * @return void
     */
    public function startup()
    {
        parent::startup();
        Configure::write('debug', 2);
        Configure::write('Cache.disable', 1);
    }

    /**
     * Get options parser
     * 
     * @return ConsoleOptionParser
     */
    public function getOptionParser()
    {
        return parent::getOptionParser()
                ->description(__d('cake_console', 'Project command'))
                ->addSubcommand('create', array(
                    'help'   => __d('cake_console', 'Create project by customized bake'),
                ))
        ;
    }

    /**
     * Create new project
     */
    public function create()
    {
        $project = '.';
        $root    = ROOT;
        $app     = APP_DIR;
        $working = APP;
        $core    = "{$root}/{$app}/Vendor/cakephp/cakephp/lib";
        $skel    = "{$core}/Cake/Console/Templates/skel";

        $this->out(__d('cake_console', '<info>Create project `%s` in `%s`</info>', $app, $working));
        
        if (!empty($project) && !Folder::isAbsolute($project) && isset($_SERVER['PWD'])) {
            $project = $_SERVER['PWD'] . DS . $project;
        }

        if ($this->_bake($project, $skel)) {
            $path = Folder::slashTerm($project);

            $this->_fixConfigureFiles($path);
            $this->_fixCakeCoreIncludePath($path);
            $this->_fixDebugKitPlugin($path);
            $this->_fixAutoloader($path);
            $this->_fixPermissionForDirs($path);
            $this->_fixGitIgnore($path);
        }
    }

    /**
     * Looks for a skeleton template of a Cake application,
     * and if not found asks the user for a path. When there is a path
     * this method will make a deep copy of the skeleton to the project directory.
     *
     * @param string $path Project path
     * @param string $skel Path to copy from
     * @param string $skip array of directories to skip when copying
     * @return boolean
     */
    protected function _bake($path, $skel, $skip = array())
    {
        $Folder = new Folder($skel);
        $app    = basename($path);

        if (!$Folder->copy(array('to' => $path, 'skip' => $skip))) {
            $this->err(__d('cake_console', "<error>Could not create</error> '%s' properly.", $app));
            return false;
        }

        foreach ($Folder->messages() as $message) {
            $this->out(String::wrap(' * ' . $message), 1, Shell::VERBOSE);
        }

        return true;
    }

    /**
     * Fix rename configure files
     * 
     * @param string $path
     * @return boolean
     */
    protected function _fixConfigureFiles($path)
    {
        if (!rename("{$path}Config/database.php.default", "{$path}Config/database.sample.php")) {
            return false;
        }

        if (!rename("{$path}Config/email.php.default", "{$path}Config/email.sample.php")) {
            return false;
        }

        $this->Project->securityCipherSeed($path);
        $this->Project->securitySalt($path);
        $this->_cachePrefix($path);

        if (!rename("{$path}Config/core.php", "{$path}Config/core.sample.php")) {
            return false;
        }
    }

    /**
     * Fix permission for `tmp` directory
     * 
     * @param string $path
     * @return boolean
     */
    protected function _fixPermissionForDirs($path)
    {
        $Folder  = new Folder($path);
        $success = true;

        if (!$Folder->chmod($path . 'tmp', 0777)) {
            $this->err(__d('cake_console', 'Could not set permissions on %s', $path . 'tmp'));
            $this->out('chmod -R 0777 ' . $path . 'tmp');
            $success = false;
        }

        return $success;
    }

    /**
     * Fix CAKE_CORE_INCLUDE_PATH in `webroot/index.php`, `webroot/test.php` and `Console/cake.php`
     * 
     * @param string $path
     * @return boolean
     */
    protected function _fixCakeCoreIncludePath($path)
    {
        $hardCode = false;
        if ($this->Project->cakeOnIncludePath()) {
            $this->out(__d('cake_console', '<info>CakePHP is on your `include_path`. CAKE_CORE_INCLUDE_PATH will be set, but commented out.</info>'));
        } else {
            $this->out(__d('cake_console', '<warning>CakePHP is not on your `include_path`, CAKE_CORE_INCLUDE_PATH will be hard coded.</warning>'));
            $this->out(__d('cake_console', 'You can fix this by adding CakePHP to your `include_path`.'));
            $hardCode = true;
        }
        $success = $this->Project->corePath($path, $hardCode) === true && $this->Project->consolePath($path) === true;
        if ($success) {
            $this->out(__d('cake_console', ' * CAKE_CORE_INCLUDE_PATH set to %s in %s', CAKE_CORE_INCLUDE_PATH, 'Console/cake.php'));
            $this->out(__d('cake_console', ' * CAKE_CORE_INCLUDE_PATH set to %s in %s', CAKE_CORE_INCLUDE_PATH, 'webroot/index.php'));
            $this->out(__d('cake_console', ' * CAKE_CORE_INCLUDE_PATH set to %s in %s', CAKE_CORE_INCLUDE_PATH, 'webroot/test.php'));
        } else {
            $this->err(__d('cake_console', 'Unable to set CAKE_CORE_INCLUDE_PATH, you should change it in %s', $path . 'Console/cake.php'));
            $this->err(__d('cake_console', 'Unable to set CAKE_CORE_INCLUDE_PATH, you should change it in %s', $path . 'webroot/index.php'));
            $this->err(__d('cake_console', 'Unable to set CAKE_CORE_INCLUDE_PATH, you should change it in %s', $path . 'webroot/test.php'));
            $success = false;
        }
        if ($success && $hardCode) {
            $this->out(__d('cake_console', '   * <warning>Remember to check these values after moving to production server</warning>'));
        }

        return $success;
    }

    /**
     * Fix class autoloader for Composer
     * 
     * @param string $path
     * @return boolean
     */
    protected function _fixAutoloader($path)
    {
        $success = true;
        $content = <<< ENDBLOCK
// Load Composer autoload.
require APP . '/Vendor/autoload.php';

// Remove and re-prepend CakePHP's autoloader as Composer thinks it is the
// most important.
// See: http://goo.gl/kKVJO7
spl_autoload_unregister(array('App', 'load'));
spl_autoload_register(array('App', 'load'), true, true);
ENDBLOCK;

        if (!file_put_contents("{$path}Config/bootstrap.php", $content, FILE_APPEND)) {
            $success = false;
        }

        return $success;
    }

    /**
     * Fix load DebugKit Plugin with `debug` > 0
     * 
     * @param string $path
     * @return boolean
     */
    protected function _fixDebugKitPlugin($path)
    {
        $content = <<< ENDBLOCK
if (Configure::read('debug') > 0) {
    CakePlugin::load('DebugKit');
}
ENDBLOCK;

        if (!file_put_contents("{$path}Config/bootstrap.php", $content, FILE_APPEND)) {
            return false;
        }
        $search = <<< ENDBLOCK
class AppController extends Controller {
}
ENDBLOCK;

        $replace = <<< ENDBLOCK
class AppController extends Controller
{
    /**
     * Contructor
     * 
     * @param CakeRequest \$request
     * @param CakeResponse \$response
     */
    public function __construct(\$request = null, \$response = null)
    {
        if (Configure::read('debug') > 0) {
            //Loads a single plugin named DebugKit
            \$this->components[] = 'DebugKit.Toolbar';
        }

        parent::__construct(\$request, \$response);
    }
}
ENDBLOCK;

        $AppController = file_get_contents("{$path}Controller/AppController.php");
        $AppController = str_replace($search, $replace, $AppController);
        if (!file_put_contents("{$path}Controller/AppController.php", $AppController)) {
            return false;
        }

        return true;
    }

    /**
     * Create `.gitignore` file if it is'not exists
     * 
     * @param string $path
     * @return boolean
     */
    protected function _fixGitIgnore($path)
    {
        if (file_exists("{$path}.gitignore")) {
            return true;
        }

        $ignore = <<< ENDBLOCK
# netbeans project 
/nbproject

# cakephp framework
/Config/database.php
/Config/email.php
/Config/core.php
/Console/phpunit
/Plugin/*
/Vendor/*
/tmp/*
/webroot/files/*

# composer
/composer.lock
/composer.phar

ENDBLOCK;

        if (file_put_contents("{$path}.gitignore", $ignore)) {
            return true;
        }

        $this->out(__d('cake_console', "<warning>Could not create `.gitignore` file</warning>"));
        return false;
    }

    /**
     * Writes cache prefix using app's name
     * 
     * @param string $path
     * @return boolean
     */
    protected function _cachePrefix($path)
    {
        $app      = basename(realpath($path));
        $File     = new File($path . 'Config' . DS . 'core.php');
        $contents = $File->read();
        if (preg_match('/(\$prefix = \'myapp_\';)/', $contents, $match)) {
            $result = str_replace($match[0], '$prefix = \'' . $app . '_\';', $contents);
            return $File->write($result);
        }
        return false;
    }

}
