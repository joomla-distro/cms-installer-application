<?php

namespace App\View\Install;

use App\App;
use App\View\DefaultHtmlView;

class InstallHtmlView extends DefaultHtmlView
{
    public function __construct($app, $model, array $paths)
    {
        parent::__construct($app, $model, $paths);

        $this->options = $this->model->getOptions();

		/*
		 * Prepare the tasks array
		 * Note: The first character of the task *MUST* be capitalised or the application will not find the task
		 */
		$this->tasks[] = ($this->options['db_old'] == 'remove') ? 'Database_remove' : 'Database_backup';
		$this->tasks[] = 'Database';

		if ($this->options['sample_file'])
		{
			$this->tasks[] = 'Sample';
		}

		$this->tasks[] = 'Config';

		if ($this->options['summary_email'])
		{
			$this->tasks[] = 'Email';
		}
    }
}
