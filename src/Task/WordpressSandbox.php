<?php namespace Brads\Robo\Task;

use Closure;
use Robo\Result;
use Robo\Output;
use RuntimeException;
use Robo\Task\Shared\DynamicConfig;
use Robo\Task\Shared\TaskInterface;

trait WordpressSandbox
{
	protected function taskWordpressSandbox(Closure $closure)
	{
		return new WordpressSandboxTask($closure);
	}
}

class WordpressSandboxTask implements TaskInterface
{
	use Output;
	use DynamicConfig;

	private $closure;

	public function __construct(Closure $closure)
	{
		$this->closure = $closure;
	}

	/**
	 * Method: run
	 * =========================================================================
	 * The main run method.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * n/a
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * mixed
	 */
	public function run()
	{
		// Serialize the closure.
		$serialized = \SuperClosure\serialize
		(
			$this->closure,
			\SuperClosure\TURBO_MODE
		);

		// Create some cross platform temp filenames
		$temp_serialized_file = tempnam(sys_get_temp_dir(), 'wpSandBoxSerialized');
		$temp_eval_file = tempnam(sys_get_temp_dir(), 'wpSandBoxEval');

		// Create our temp eval file
		$php = '<?php $c = unserialize(file_get_contents("'.$temp_serialized_file.'")); echo json_encode($c());';

		// Save our tmp files
		file_put_contents($temp_serialized_file, $serialized);
		file_put_contents($temp_eval_file, $php);

		// Build the command to run
		$cmd = './vendor/bin/wp eval-file '.$temp_eval_file;

		// Run the cmd
		$descriptorspec = [1 => ["pipe", "w"], 2 => ["pipe", "w"]];
		$process = proc_open($cmd, $descriptorspec, $pipes);
		if (is_resource($process))
		{
			$output = [];

			$output['stdout'] = stream_get_contents($pipes[1]);
			fclose($pipes[1]);

			$output['stderr'] = stream_get_contents($pipes[2]);
			fclose($pipes[2]);

			proc_close($process);
		}
		else
		{
			$output = false;
		}

		// Delete the tmp files
		unlink($temp_serialized_file);
		unlink($temp_eval_file);

		// Check for errors
		if (!$output)
		{
			throw new RuntimeException('Failed to run wp-cli! - ('.$cmd.')');
		}
		elseif(!empty($output['stderr']))
		{
			throw new RuntimeException($output['stderr']);
		}

		// Unserialize the output
		return json_decode($output['stdout']);
	}
}