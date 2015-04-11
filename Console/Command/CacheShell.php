<?php

App::uses('AppShell', 'Console/Command');
App::uses('Cache', 'Cache');

/**
 * Class CacheShell
 * 
 * Working with cache by console commands
 * 
 * @author OanhNN <oanhnn@rikkeisoft.com>
 * @version 1.0
 */
class CacheShell extends AppShell
{

    /**
     * Displays a header for the shell
     *
     * @return void
     */
    protected function _welcome()
    {
        $this->out();
        $this->out(__d('cake_console', '<info>Welcome to CakePHP %s Console</info>', 'v' . Configure::version()));
        $this->out(__d('cake_console', '<info>Customize by RikkeiSoft Co. Ltd.,</info>', 'v' . Configure::version()));
        $this->hr();
        $this->out(__d('cake_console', 'App : %s', APP_DIR));
        $this->out(__d('cake_console', 'Path: %s', APP));
        $this->hr();
    }

    /**
     * Get options parser
     * 
     * @return ConsoleOptionParser
     */
    public function getOptionParser()
    {
        $options = array(
            'force' => array(
                'help'    => __d('cake_console', 'Run force'),
                'short'   => 'f',
                'default' => false,
            ),
            'clear-session' => array(
                'help'    => __d('cake_console', 'Clear all session'),
                'short'   => 's',
                'default' => false,
            ),
        );

        return parent::getOptionParser()
                ->description(__d('cake_console', 'Cache command'))
                ->addSubcommand('clear', array(
                    'help'   => __d('cake_console', 'Clear cache'),
                    'parser' => compact('options')
                ))
        ;
    }

    /**
     * Clear cache task
     */
    public function clear()
    {
        $force        = empty($this->params['force']) ? false : true;
        $clearSession = empty($this->params['clear-session']) ? false : true;
        $configs      = Cache::configured();

        foreach ($configs as $config) {
            if ('_cache_session_' == $config && !$clearSession) {
                continue;
            }
            if ($force) {
                Cache::clear(false, $config);
            } else {
                $prompt = __d('cake_console', 'Are you want clear cache of config %s ?', $config);
                if ('y' == $this->in($prompt, array('y', 'n'), 'y')) {
                    $cleared = Cache::clear(false, $config);
                    if ($cleared) {
                        $this->out(__d('cake_console', 'Cleared success cache of config %s', $config));
                    } else {
                        $this->out(__d('cake_console', 'Cleared failture cache of config %s', $config));
                    }
                }
            }
        }
    }

}
