<?php

namespace alsvanzelf\debugtoolbar\parts;

use alsvanzelf\debugtoolbar\models\Detail;
use alsvanzelf\debugtoolbar\models\Metric;
use alsvanzelf\debugtoolbar\models\PartAbstract;
use alsvanzelf\debugtoolbar\models\PartInterface;

/**
 * @todo add process() (store a processed version for easier display and re-usage between methods)
 */
class RequestPart extends PartAbstract implements PartInterface {
	const DURATION_ALERT = 0.5;
	
	public function name() {
		return 'Request';
	}
	
	public function title() {
		return 'Request';
	}
	
	public static function track() {
		return array_merge(
			self::trackRequest(),
			self::trackDuration(),
			self::trackMemory(),
			self::trackGit()
		);
		
		$logger->pushProcessor(new WebProcessor());
	}
	
	public function metrics() {
		$durationAlert = ($this->logData->duration_total > self::DURATION_ALERT) ? '> '.self::DURATION_ALERT.' s' : null;
		$memoryAlert   = (self::memoryStringToBytes($this->logData->memory_peak) > self::memoryStringToBytes(ini_get('memory_limit')) / 4) ? '> 25% of set memory limit ('.ini_get('memory_limit').')' : null;
		$gitAlert      = (strpos($this->logData->git_branch, 'HEAD detached at') !== false) ? 'HEAD detached' : null;
		
		switch ($this->logData->request_method) {
			case 'GET':    $requestMethod = $this->logData->request_method.' ';                                      break;
			case 'POST':   $requestMethod = '<span class="text-primary">'.$this->logData->request_method.'</span> '; break;
			case 'PUT':
			case 'PATCH':  $requestMethod = '<span class="text-info">'   .$this->logData->request_method.'</span> '; break;
			case 'DELETE': $requestMethod = '<span class="text-danger">' .$this->logData->request_method.'</span> '; break;
			default:       $requestMethod = '<code>'                     .$this->logData->request_method.'</code> '; break;
		}
		
		$requestType = '';
		if ($this->logData->request_type !== null) {
			$requestType .= ' <span class="label label-default">'.$this->logData->request_type.'</span>';
		}
		
		$memoryDetail = new Detail($key='memory', $mode=Detail::MODE_INLINE);
		
		$metrics = [
			new Metric('Request',  $requestMethod.$this->logData->request_url.$requestType),
			new Metric('Duration', round($this->logData->duration_total, 3).' s', $featured=true, $durationAlert),
			new Metric('Memory',   $this->logData->memory_peak.' (peak)', $featured=true, $memoryAlert, $memoryDetail),
			new Metric('Git',      $this->logData->git_branch.' <code>'.$this->logData->git_commit.'</code>', $featured=false, $gitAlert),
		];
		
		return $metrics;
	}
	
	public function detail(Detail $detail) {
		switch ($detail->key) {
			case 'memory': return $this->detailMemory();
			default:       throw new Exception('unknown detail key');
		}
	}
	
	private function detailMemory() {
		$data = [
			'memoryCurrentMetric' => $this->metricMemoryCurrent(),
			'memoryPeakMetric'    => $this->metricMemoryPeak($forDetail=true),
		];
		
		return $data;
	}
	
	private function metricMemoryCurrent() {
		$alert  = (self::memoryStringToBytes($this->logData->memory_current) > self::memoryStringToBytes(ini_get('memory_limit')) / 4) ? '> 25% of set memory limit ('.ini_get('memory_limit').')' : null;
		$metric = new Metric('During <code>Log::track</code>', $this->logData->memory_current, $featured=false, $alert);
		
		return $metric;
	}
	
	private function metricMemoryPeak($forDetail=false) {
		$title    = ($forDetail) ? 'Peak' : 'Memory';
		$value    = ($forDetail) ? $this->logData->memory_peak : $this->logData->memory_peak.' (peak)';
		$featured = true;
		$alert    = (self::memoryStringToBytes($this->logData->memory_peak) > self::memoryStringToBytes(ini_get('memory_limit')) / 4) ? '> 25% of set memory limit ('.ini_get('memory_limit').')' : null;
		$detail   = new Detail($key='memory', $mode=Detail::MODE_INLINE);
		
		$metric = new Metric($title, $value, $featured, $alert, $detail);
		
		return $metric;
	}
	
	private static function memoryStringToBytes($byteString) {
		preg_match('{(?<bytes>[0-9.]+) ?(?<unit>[A-Z])}', $byteString, $match);
		$bytes = (float) $match['bytes'];
		
		if (empty($match['unit']) || $match['unit'] == 'B') {
			return $bytes;
		}
		
		$units    = ['K'=>1, 'M'=>2, 'G'=>3, 'T'=>4];
		$exponent = $units[$match['unit']];
		$bytes    = $bytes * pow(1024, $exponent);
		
		return $bytes;
	}
	
	private static function memoryBytesToString($bytes) {
		if ($bytes > 1024 * 1024) {
			return round($bytes / 1024 / 1024, 2).' MB';
		}
		elseif ($bytes > 1024) {
			return round($bytes / 1024, 2).' KB';
		}
		
		return $bytes . ' B';
	}
	
	private static function trackDuration() {
		return [
			'duration_total' => isset($_SERVER['REQUEST_TIME_FLOAT']) ? (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) : null,
		];
	}
	
	private static function trackGit() {
		$branchName = `git symbolic-ref --short HEAD`;
		$commitHash = `git rev-parse --short HEAD`;
		
		return [
			'git_branch' => $branchName,
			'git_commit' => $commitHash,
		];
	}
	
	private static function trackMemory() {
		return [
			'memory_current' => self::memoryBytesToString(memory_get_usage($real_usage=true)),
			'memory_peak'    => self::memoryBytesToString(memory_get_peak_usage($real_usage=true)),
		];
	}
	
	private static function trackRequest() {
		return [
			'request_method' => isset($_SERVER['REQUEST_METHOD'])        ? $_SERVER['REQUEST_METHOD']        : null,
			'request_url'    => isset($_SERVER['REQUEST_URI'])           ? $_SERVER['REQUEST_URI']           : null,
			'request_type'   => isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : null,
		];
	}
}
