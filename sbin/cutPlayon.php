#!/usr/bin/env php
<?php

define('SCRIPT_START', microtime(1));
setlocale(LC_ALL, 'en_US.UTF-8');
$stdOut = true;

$currentDirectory = getcwd();
$currentDirectory = rtrim($currentDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
setupBuffering($currentDirectory . 'cutPlayon.log', 'Cut PlayOn');
ul('Current Directory: ' . $currentDirectory);
$backupDirectory = $currentDirectory . 'backup' . DIRECTORY_SEPARATOR;
if (!is_dir($backupDirectory)) {
	mkdir($backupDirectory);
}

$videos = listDirectoryContents($currentDirectory);
$numVideos = count($videos);

ul($numVideos . ' Video' . ($numVideos === 1 ? '' : 's') . ' Found');

if (!$numVideos) {
	exit;
}

foreach ($videos as $video) {
	smallHeader(basename($video));
	$video = renameVideo($video);
	$backup = backupVideo($video);
	if (!$backup) {
		ul('Unable to create a backup!', 1);
		ul('---------- ERROR ----------');
		continue;
	}

	$chapters = getVideoChapters($backup);
	if (!is_array($chapters) || empty($chapters) || !is_array($chapters['chapters'])) {
		ul('Unable to read video info.', 1);
		ul('---------- ERROR ----------');
		continue;
	}

	$chapterFile = loopChapters($video, $chapters, $backup);
	$chapterFileLines = file($chapterFile);
	if (empty($chapterFileLines) || count($chapterFileLines) % 3 !== 0) {
		ul('Error writing chapter file.', 1);
		ul('---------- ERROR ----------');
		continue;
	}

	if (convertVideo($video, $chapterFile)) {
		ul('+++++ SUCCESS! +++++');
		unlink($chapterFile);
	} else {
		ul('---------- ERROR ----------');
	}
}

function renameVideo($video)
{
	$video = basename($video);
	$videoRenamed = preg_replace('/[^a-z0-9 .-_]/i', '-', $video);
	if ($videoRenamed !== $video) {
		rename($video, $videoRenamed);
		ul('Renamed: ' . $videoRenamed);
		return $videoRenamed;
	}

	return $video;
}

function convertVideo($video, $chapterFile)
{
	return runProcess('ffmpeg -f concat -safe 0 -i ' . escapeshellarg($chapterFile) . ' -c copy -c:a copy -y ' . escapeshellarg($video), '', false, true, false, 1);
}

function loopChapters($video, $chapters, $backup)
{
	$format = $chapters['format'];
	$chapters = array_values(
		array_filter(
			$chapters['chapters'],
			function ($chapter) {
				return ($chapter['tags']['title'] !== 'Advertisement');
			}
		)
	);

	$chapterFile = substr($video, 0, -4) . '.txt';
	if (is_file($chapterFile)) {
		unlink($chapterFile);
	}

	$chapterCount = count($chapters);
	ul($chapterCount . ' chapter' . ($chapterCount === 1 ? '' : 's'));
	$backupEscaped = addcslashes($backup, ' \\\'');
	if (!$chapterCount) {
		$format['start_time'] += 5.0;
		$format['duration'] -= 5.0;
		writeChapter($chapterFile, $backupEscaped, $format['start_time'], $format['duration']);
	} else {
		for ($i = 0; $i < $chapterCount; $i++) {
			$chapter = $chapters[$i];
			if ($i === 0) {
				//ul('Altering start time.', 1);
				$chapter['start_time'] += 5.0;
			}

			if ($i === ($chapterCount - 1)) {
				//ul('Altering end time.', 1);
				$chapter['end_time'] -= 5.0;
			}

			writeChapter($chapterFile, $backupEscaped, $chapter['start_time'], $chapter['end_time']);
		}
	}

	return $chapterFile;
}

function writeChapter($chapterFile, $backupEscaped, $startTime, $endTime)
{
	ul($startTime . ' - ' . $endTime, 1);
	file_put_contents(
		$chapterFile,
		'file ' . $backupEscaped . '' . PHP_EOL .
			'inpoint ' . $startTime . PHP_EOL .
			'outpoint ' . $endTime . PHP_EOL,
		FILE_APPEND
	);
}


function getVideoChapters($file)
{
	$output = runProcess('ffprobe -i ' . escapeshellarg($file) . ' -print_format json -show_chapters -show_format -loglevel error', '', false, true, true, 2);
	return json_decode(implode($output), true);
}

function backupVideo($video = '')
{
	global $currentDirectory, $backupDirectory;
	$backupFileName = $backupDirectory . $video;
	if (!is_file($backupFileName)) {
		ul('Making a backup...');
		return (copy($currentDirectory . $video, $backupFileName) ? $backupFileName : false);
	}

	return $backupFileName;
}

//======================================================================================================================

function now($echo = false)
{
	$now = date('Y-m-d H:i:s');
	if ($echo) {
		echo $now;
	}

	return $now;
}

function textHeader($text = '')
{
	echo "\n# ", $text, "\n\n";
	fl();
}

function smallHeader($text = '')
{
	echo "\n## ", $text, "\n\n";
	fl();
}

function smallerHeader($text = '')
{
	echo "\n### ", $text, "\n\n";
	fl();
}

function smallestHeader($text = '')
{
	echo "\n#### ", $text, "\n\n";
	fl();
}

function line($text = '')
{
	echo $text, "\n";
	fl();
}

function hr()
{
	echo "\n----\n";
	fl();
}

function bold($text = '')
{
	return '**' . $text . '**';
}

function ol($text = '', $level = 0)
{
	for ($i = 0; $i < $level; $i++) {
		echo '   ';
	}

	echo ' 1. ', $text, "\n";
	fl();
}

function ul($text = '', $level = 0)
{
	for ($i = 0; $i < $level; $i++) {
		echo '  ';
	}

	echo ' * ', $text, "\n";
	fl();
}

function filterAlphabetical($string = '')
{
	return preg_replace('/[^a-z]/i', '', $string);
}

function filterAlphaNum($string = '')
{
	return preg_replace('/[^a-z0-9]/i', '', $string);
}

function filterNumber($number = '')
{
	return preg_replace('/[^0-9]/i', '', $number);
}

/**
 * Executes a command on the command line.
 *
 * @param string     $command        The command to execute.
 * @param string     $errorMessage   Error message to output on failure. If not empty, the script will exit on failure.
 * @param bool|false $passThrough    Set to true to pass the output through while the command executes rather than buffering.
 * @param bool|true  $redirectStdErr Redirect standard error to standard output so that all output is captured.
 * @param bool|false $returnOutput   Set to true to return the output as an array of lines rather than the success status.
 * @param int        $quiet          Set to 1 to suppress output unless the command returns an error status.
 *                                   Set to 2 to suppress all output no matter what.
 *
 * @return array|bool True on success, false on failure, or the output as an array of strings if $returnOutput is true.
 */
function runProcess($command = '', $errorMessage = '', $passThrough = false, $redirectStdErr = true, $returnOutput = false, $quiet = 0)
{
	// Error Status
	$status = 1;
	if ($passThrough && !$returnOutput && $quiet === 0) {
		if (!empty($command)) {
			line('```');
			passthru($command . ($redirectStdErr ? ' 2>&1' : ''), $status);
			fl();
			line('```');
		}
	} else {
		$output = array();
		if (!empty($command)) {
			exec($command . ($redirectStdErr ? ' 2>&1' : ''), $output, $status);
			if ($quiet !== 2 && !empty($output) && ($quiet !== 1 || $status !== 0)) {
				$skipOutput = getSkipOutput();
				line('```');
				foreach ($output as $o) {
					if (!in_array($o, $skipOutput)) {
						line($o);
					}
				}

				line('```');
			}
		}
	}

	if ($status !== 0 && !empty($errorMessage)) {
		fatalError($errorMessage, 102);
	}

	return ($returnOutput ? $output : $status === 0);
}

function listDirectoryContents($directory = '')
{
	return (!empty($directory) && is_readable($directory) && is_dir($directory)
		? array_filter(
			scandir($directory),
			function ($entry) {
				$skipEntries = array('.', '..', '.svn', '.git', '.gitkeep');
				return (!in_array($entry, $skipEntries) && strtolower(substr($entry, -4)) === '.mp4');
			}
		)
		: array());
}

function ignoreEntries($entry)
{
	$skipEntries = array('.', '..', '.svn', '.git', '.gitkeep');
	return (!in_array($entry, $skipEntries) && strtolower(substr($entry, -4)) === '.mp4');
}

function getSkipOutput()
{
	return array(
		'',
		'Bye',
	);
}

$obFile;
$scriptTitle = '';
$bufferingEnded = false;
function setupBuffering($file = '', $title = '')
{
	global $obFile, $scriptTitle;
	if (empty($file)) {
		return;
	}

	$scriptTitle = $title;
	$obFile = fopen($file, 'a');
	register_shutdown_function('endBuffering');
	ob_start('obFileCallback');
	hr();
	textHeader(now() . (!empty($scriptTitle) ? ' ' . $scriptTitle : ''));
}

function suppressStdOut()
{
	global $stdOut;
	$stdOut = false;
}

function obFileCallback($buffer)
{
	global $obFile, $stdOut;
	fwrite($obFile, $buffer);
	if ($stdOut) {
		fwrite(STDOUT, $buffer);
	}
}

function endBuffering()
{
	global $obFile, $scriptTitle, $stdOut, $bufferingEnded;
	if ($bufferingEnded) {
		return;
	}

	line();
	textHeader(now() . (!empty($scriptTitle) ? ' ' . $scriptTitle : '') . ' Finished');
	line('Runtime: ' . getPrettyTime(round((microtime(1) - SCRIPT_START))));
	ob_end_flush();
	if ($stdOut) {
		$fileInfo = stream_get_meta_data($obFile);
		line('This output has been logged to: ' . $fileInfo['uri']);
		line();
	}

	$bufferingEnded = true;
}

function fl()
{
	flush();
	if (ob_get_level() !== 0) {
		ob_flush();
	}
}

function getPrettyTime($seconds)
{
	$time = '';
	$times = array();

	//Get number of years
	$yr = floor($seconds / 31536000);
	$yr = ($yr > 0 ? $yr . ' year' . ($yr > 1 ? 's' : '') : '');
	if (!empty($yr)) {
		$times[] = $yr;
	}

	$remainder = ($seconds % 31536000);

	//Get number of weeks
	$wk = floor($remainder / 604800);
	$wk = ($wk > 0 ? $wk . ' week' . ($wk > 1 ? 's' : '') : '');
	if (!empty($wk)) {
		$times[] = $wk;
	}

	$remainder = ($remainder % 604800);

	//Get number of days
	$dy = floor($remainder / 86400);
	$dy = ($dy > 0 ? $dy . ' day' . ($dy > 1 ? 's' : '') : '');
	if (!empty($dy)) {
		$times[] = $dy;
	}

	$remainder = ($remainder % 86400);

	//Get number of hours
	$hr = floor($remainder / 3600);
	$hr = ($hr > 0 ? $hr . ' hour' . ($hr > 1 ? 's' : '') : '');
	if (!empty($hr)) {
		$times[] = $hr;
	}

	$remainder = ($remainder % 3600);

	//Get number of minutes
	$min = floor($remainder / 60);
	$min = ($min > 0 ? $min . ' minute' . ($min > 1 ? 's' : '') : '');
	if (!empty($min)) {
		$times[] = $min;
	}

	//Get number of seconds
	$sec = ($remainder % 60);
	$sec = ($sec . ' second' . ($sec != 1 ? 's' : ''));
	if (!empty($sec)) {
		$times[] = $sec;
	}

	$y = count($times);
	for ($x = 0; $x < $y; $x++) {
		if ($x != 0) {
			if ($y > 2) {
				$time .= ',';
			}

			$time .= ' ';
		}

		if ($x != 0 && $x == ($y - 1)) {
			$time .= 'and ';
		}

		$time .= $times[$x];
	}

	return $time;
}

function getInput($msg = '')
{
	fwrite(STDOUT, $msg . ': ');
	$varin = trim(fgets(STDIN));
	return $varin;
}

function pause()
{
	fwrite(STDOUT, 'Press [Enter] to continue or [CTRL-C] to abort...');
	fgets(STDIN);
}
