<?php 
function parseJson($path, $filename)
{
#Get json object from each file path
	$file_path = $path . "/" . $filename . "*";
	$file = popen("/bin/ls ".$file_path, "r");
	$jsonobj = array();

	while(! feof($file)) {
		$filepath = trim(fgets($file));
		if (empty($filepath)) {
			continue;
		}
		$str = file_get_contents($filepath);
		array_push($jsonobj, json_decode($str, true));
	}
	pclose($file);
	return $jsonobj;
}

function MergeJsonObj($jsonobj)
{
#Merge json object
	$out = array();
	foreach ($jsonobj as $el) {

		foreach ($el as $k => $kval) {
			if (!isset($out[$k])) {
				$out[$k] = $kval;
				continue;
			}
			if (is_array($kval)) {
				foreach ($kval as $sub_k => $sub_kval) {
					if (!isset($out[$k][$sub_k])) {
						$out[$k][$sub_k] = $sub_kval;
					} else {
						$out[$k][$sub_k] += $sub_kval;
					}
				}
			} else {
				$out[$k] += $kval;
			}
		}
	}
	return $out;
}

function getValue($jsonstr, $field_name)
{
	if (isset($jsonstr[$field_name])) {
		return floatval($jsonstr[$field_name]);
	} else {
		return 0;
	}
}
function DealTimeHasing($CWorkerObj)
{
	$out = $CWorkerObj;
	foreach ($CWorkerObj as $key=>$el) {
		$time_client_save_chunk = getValue($el, "IDX_SAVE_FILECHUNK");
		$time_hashing = getValue($el, "IDX_HASH_BLOCK");
		$time_serializing = getValue($el, "IDX_BACKUP_DATA_SERIALIZE");
	
		if ($time_hashing > 0) {
			if ($time_client_save_chunk > 0 && $time_hashing > $time_client_save_chunk) { # local backup
				$time_hashing -= $time_client_save_chunk;
			}
			if ($time_hashing > $time_serializing) { # remote backup, for small file backup (no flush happen during chunking)↲
				$time_hashing -= $time_serializing;
			}
			$out[$key]["IDX_HASH_BLOCK"] = $time_hashing;
			$out[$key]["Save Chunks"] = $time_client_save_chunk;
		}
	}	
	return $out;
}

function CreateBarChart($barName, $Number)
{
	$out = array(array('thread'));
	for ($i=1; $i <=$Number; $i++)
		array_push($out,array(strVal($i)));
	return $out;
}

function pushBarChartKeyValue(&$bar, $key_new, $key_old, $workerObj)
{
	array_push($bar[0], $key_new);
	foreach ($workerObj as $objindex=>$objArray)
		array_push($bar[$objindex+1], getValue($objArray, $key_old));
}

$DataPath = $_GET["datapath"];
DataPath = "snoopy_1M_8proc_1st";

#read file
$SMaster = MergeJsonObj(parseJson($DataPath, "imgbkp_SMaster.profile.json"));
$SWorker = MergeJsonObj(parseJson($DataPath, "imgbkp_SWorker.profile.json"));
$BController = MergeJsonObj(parseJson($DataPath, "imgbkp_BackupCtrl.profile.json")); 
$CWorkerObj = parseJson($DataPath, "imgbkp_CWorker.profile.json");
$CWorkerObj = DealTimeHasing($CWorkerObj);
$CWorker = MergeJsonObj($CWorkerObj);

$Total = getValue($BController, "IDX_BACKUP_TOTAL");


#Client ratio
$client_backup_prepare = getValue($BController, "IDX_BACKUP_PREPARE");

$backup_overview = array(array('Action', 'Seconds'),
		array('Prepare Backup', $client_backup_prepare),  	# IDX_CACULATE_SIZE + SYNC LOCAL DB + Worker ready
		array('Do Backup', $Total - $client_backup_prepare));	# IDX_BACKUP_TOTAL - IDX_BACKUP_PREPARE

$client_controller = array(array('Action', 'Seconds'),
		array('Caculate Size', getValue($BController, "IDX_CACULATE_SIZE")),
		array('Traverse & Dispatch File Dir', getValue($BController, "IDX_FILEDIR_TRAVERSE") - getValue($BController, "IDX_FILEDIR_TRAVERSE_WAIT")),
		array('Waiting for Dispatch', getValue($BController, "IDX_FILEDIR_TRAVERSE_WAIT")));

# backup cmd's delay: 
# 	- client: bufferevent write to socket + kernel/NIC process.
#	- network: RTT delay.
#	- server: bufferevent read from socket + server read from bufferevent + parsing header. 
$server_total = getValue($SWorker, "IDX_BACKUP_DATA_DE_SERIALIZE")
		+ getValue($SWorker, "IDX_SAVE_FILECHUNK")
		+ getValue($SWorker, "IDX_BUFFER_READ")
		+ getValue($SWorker, "IDX_BUFFER_WRITE")
		+ getValue($SWorker, "IDX_MSG_SERIALIZE")
		+ getValue($SWorker, "IDX_MSG_DE_SERIALIZE");

# NIC transfer & buffer read are pipeline.
$socket_delay = getValue($CWorker, "IDX_BACKUP_WAIT_SERVER") - $server_total + getValue($SWorker, "IDX_BUFFER_READ");

#Client action's ratio
$client_worker =  array(array('Action', 'Seconds'),
		array('File Stat', getValue($CWorker, "IDX_STAT")),
		array('File Change Stat', getValue($CWorker, "IDX_GET_CHG_STATUS")), 
		array('Get Candidate Chunk', getValue($CWorker, "IDX_GET_CANDCHUNK")),
		array('Read File', getValue($CWorker, "IDX_READ_FILE")),
		array('Hashing Block', getValue($CWorker, "IDX_HASH_BLOCK")),
		array('Move Memory', getValue($CWorker, "IDX_MEM_MOVE")),
		array('Serialize Backup Data', getValue($CWorker, "IDX_BACKUP_DATA_SERIALIZE")),
#		array('Chunking', getValue($CWorker, "IDX_DO_CHUNK")),	
		array('Wait for Server', getValue($CWorker, "IDX_BACKUP_WAIT_SERVER")),
		array('Update Local DB', getValue($CWorker, "IDX_UPDATE_LOCALDB")),
		array('Queuing Jobs', getValue($CWorker, "IDX_QUEUING_JOB")),
		array('Add file into list', getValue($CWorker, "IDX_ADD_FILE")),
		array('Add callback', getValue($CWorker, "IDX_ADD_CALLBACK")),
		array('Open File', getValue($CWorker, "IDX_OPEN_FILE")),
		array('Read Buffer Event', getValue($CWorker, "IDX_BUFFER_READ")),
		array('Write Buffer Event', getValue($CWorker, "IDX_BUFFER_WRITE")),
		array('Protobuf Serialize', getValue($CWorker, "IDX_MSG_SERIALIZE")),
		array('Available', getValue($CWorker, "IDX_MSG_SERIALIZE")),
		array('Save Chunks', getValue($CWorker, "Save Chunks")),
		array('Wait for buffer available', getValue($CWorker, "IDX_BACKUP_PIPELINE_BUF_AVAIL_WAIT")),
		array('Protobuf De-serialize', getValue($CWorker, "IDX_MSG_DE_SERIALIZE")));

$client_worker_thread =  CreateBarChart("tread", count($CWorkerObj));
pushBarChartKeyValue($client_worker_thread, 'File Stat', "IDX_STAT", $CWorkerObj);
pushBarChartKeyValue($client_worker_thread, 'File Change Stat', "IDX_GET_CHG_STATUS", $CWorkerObj);
pushBarChartKeyValue($client_worker_thread, 'Get Candidate Chunk', "IDX_GET_CANDCHUNK", $CWorkerObj);
pushBarChartKeyValue($client_worker_thread, 'Read File', "IDX_READ_FILE", $CWorkerObj);
pushBarChartKeyValue($client_worker_thread, 'Hashing Block', "IDX_HASH_BLOCK", $CWorkerObj);
pushBarChartKeyValue($client_worker_thread, 'Move Memory', "IDX_MEM_MOVE", $CWorkerObj);
pushBarChartKeyValue($client_worker_thread, 'Serialize Backup Data', "IDX_BACKUP_DATA_SERIALIZE", $CWorkerObj);
pushBarChartKeyValue($client_worker_thread, 'Wait for Server', "IDX_WAIT_SERVER", $CWorkerObj);
pushBarChartKeyValue($client_worker_thread, 'Update Local DB', "IDX_UPDATE_LOCALDB", $CWorkerObj);
pushBarChartKeyValue($client_worker_thread, 'Queuing Jobs', "IDX_QUEUING_JOB", $CWorkerObj);
pushBarChartKeyValue($client_worker_thread, 'Add file into list', "IDX_ADD_FILE", $CWorkerObj);
pushBarChartKeyValue($client_worker_thread, 'Add callback', "IDX_ADD_CALLBACK", $CWorkerObj);
pushBarChartKeyValue($client_worker_thread, 'Open File', "IDX_OPEN_FILE", $CWorkerObj);
pushBarChartKeyValue($client_worker_thread, 'Read Buffer Event', "IDX_BUFFER_READ", $CWorkerObj);
pushBarChartKeyValue($client_worker_thread, 'Write Buffer Event', "IDX_BUFFER_WRITE", $CWorkerObj);
pushBarChartKeyValue($client_worker_thread, 'Protobuf Serialize', "IDX_MSG_SERIALIZE", $CWorkerObj);
pushBarChartKeyValue($client_worker_thread, 'Available', "IDX_MSG_SERIALIZE", $CWorkerObj);
pushBarChartKeyValue($client_worker_thread, 'Save chunks', "Save chunks", $CWorkerObj);
pushBarChartKeyValue($client_worker_thread, 'Wait for buffer available', "IDX_BACKUP_PIPELINE_BUF_AVAIL_WAIT", $CWorkerObj);
pushBarChartKeyValue($client_worker_thread, 'Protobuf De-serialize', "IDX_MSG_DE_SERIALIZE", $CWorkerObj);
#print_r($client_worker_thread);

$client_thread_total = CreateBarChart("thread", count($CWorkerObj));
pushBarChartKeyValue($client_thread_total, "Total Time", "IDX_BACKUP_TOTAL", $CWorkerObj);
#Client Backup action's ratio
$client_worker_chunking = array(array('Action', 'Seconds'));

#Dedup lib's profiling
if (isset($CWorker["Dedup"])) {
	$client_worker_chunking = array(array('Action', 'Seconds'),
			array('-FILE_MODIFY Virtual-File DB operation', getValue($CWorker['Dedup'], '-FILE_MODIFY Virtual-File DB operation')),
			array('-FILE_META Virtual-File DB operation', getValue($CWorker['Dedup'], '-FILE_META Virtual-File DB operation')),
			array('-FILE_UNCHANGE Virtual-File DB operation', getValue($CWorker['Dedup'], '-FILE_UNCHANGE Virtual-File DB operation')),
			array('-FILE_NEW Virtual-File DB operation', getValue($CWorker['Dedup'], '-FILE_NEW Virtual-File DB operation')),
			array('Virtual File total operation', getValue($CWorker['Dedup'], 'Virtual File total operation')),
			array('--Pools New Bucket Open', getValue($CWorker['Dedup'], '--Pool New Bucket Open')),
			array('--Pools New Chunk DB writing', getValue($CWorker['Dedup'], '--Pool New Chunk DB writing')),
			array('--Pools New Chunk Bucket writing', getValue($CWorker['Dedup'], '--Pool New Chunk Bucket writing')),
			array('-Pools New Chunk totoal operation', getValue($CWorker['Dedup'], '-Pool New Chunk totoal operation')),
			array('-Pools Ref-count Update', getValue($CWorker['Dedup'], '-Pool Ref-count Update')),
			array('ChunkPool total operation', getValue($CWorker['Dedup'], 'ChunkPool total operation')),
			array('CandChunk DB writing', getValue($CWorker['Dedup'], 'CandChunk DB writing')),
			array('CandChunk Asking', getValue($CWorker['Dedup'], 'CandChunk Asking')),
			array('-Backup file reading', getValue($CWorker['Dedup'], '-Backup file reading')),
			array('-Break point search', getValue($CWorker['Dedup'], '-Break point search')),
			array('-CDC other cost', getValue($CWorker['Dedup'], '-CDC other cost')),
			array('-md5', getValue($CWorker['Dedup'], '-md5')),
			array('ClientDB writing', getValue($CWorker['Dedup'], 'ClientDB writing')),
			array('Version List DB total operation', getValue($CWorker['Dedup'], 'Version List DB total operation')),
			array('Write Buffer copy', getValue($CWorker['Dedup'], 'Write Buffer copy')),
			array('Cand Index insert', getValue($CWorker['Dedup'], 'Cand Index insert')),
			array('Status check and metadata get', getValue($CWorker['Dedup'], 'Status check and metadata get')),
			array('-Share name get', getValue($CWorker['Dedup'], '-Share name get')),
			array('-Progress', getValue($CWorker['Dedup'], '-Progress')),
			array('-BackupSkipFilter overhead', getValue($CWorker['Dedup'], '-BackupSkipFilter overhead')),
			array('nftw and others', getValue($CWorker['Dedup'], 'nftw and others')));
}

$client_worker_serialize = array(array('Action', 'Seconds'),
			array('Count message size', getValue($CWorker, "IDX_COUNT_BODY_SIZE")),
			array('Serialize file list', getValue($CWorker, "IDX_COUNT_FILE_LIST")),
			array('Serialize chunk list', getValue($CWorker, "IDX_COUNT_CHUNK_LIST")));

#Server action's ratio
$server = array(array('Action', 'Seconds'),
		array('Prepare Backup', getValue($SMaster, "IDX_BACKUP_PREPARE")),
		array('De-Serialize Backup Data', getValue($SWorker, "IDX_BACKUP_DATA_DE_SERIALIZE")),
		array('Save Chunks', getValue($SWorker, "IDX_SAVE_FILECHUNK")),
		array('Read Buffer Event', getValue($SWorker, "IDX_BUFFER_READ")),
		array('Write Buffer Event', getValue($SWorker, "IDX_BUFFER_WRITE")),
		array('Protobuf Serialize', getValue($SWorker, "IDX_MSG_SERIALIZE")),
		array('Protobuf De-serialize', getValue($SWorker, "IDX_MSG_DE_SERIALIZE")));

#Output
$total_output = $Total . " seconds";
$worker_total_output = getValue($CWorker, "IDX_BACKUP_TOTAL") . " seconds";
$unknown_delay = getValue($CWorker, "IDX_BACKUP_TOTAL")
				- getValue($CWorker, "IDX_STAT")
				- getValue($CWorker, "IDX_GET_CHG_STATUS")
				- getValue($CWorker, "IDX_GET_CANDCHUNK")
				- getValue($CWorker, "IDX_DO_CHUNK")
				- getValue($CWorker, "IDX_BACKUP_WAIT_SERVER")
				- getValue($CWorker, "IDX_UPDATE_LOCALDB")
				- getValue($CWorker, "IDX_QUEUING_JOB")
				- getValue($CWorker, "IDX_ADD_FILE")
				- getValue($CWorker, "IDX_ADD_CALLBACK")
				- getValue($CWorker, "IDX_OPEN_FILE")
				- getValue($CWorker, "IDX_BUFFER_READ")
				- getValue($CWorker, "IDX_BUFFER_WRITE")
				- getValue($CWorker, "IDX_MSG_SERIALIZE")
				- getValue($CWorker, "IDX_MSG_DE_SERIALIZE");
$unknown_delay .= " seconds";

$server_total .= " seconds";

$socket_delay = $socket_delay . " seconds";

$diagram = array(array('title'=>'Backup Overview', 'value'=>$backup_overview),
		array('title'=>'Client Controller', 'value'=>$client_controller),
		array('title'=>'Client Worker', 'value'=>$client_worker),
		array('title'=>'Server', 'value'=>$server),
#		array('title'=>'Client Worker: File Read & Chunking', 'value'=>$client_worker_backup),
		array('title'=>'Client Worker: Dedup Detail - Chunking', 'value'=>$client_worker_chunking),
		array('title'=>'Client Worker: Dedup Detail - Serialize', 'value'=>$client_worker_serialize));

$barChart = array(array('title'=>'Client Worker Thread', 'isStacked'=>'true', 'value'=>$client_worker_thread),
			array('title'=>'Client Thread Total Time', 'hAxis'=>array( 'title'=>"Total Population", 'minValue'=> 0), 'value'=>$client_thread_total));

$data = array('diagram'=>$diagram, 
		'barChart'=>$barChart,
		'total'=>$total_output, 
		'worker_total'=>$worker_total_output,
		'server_total'=>$server_total,
		'socket_delay'=>$socket_delay,
		'unknown_delay'=>$unknown_delay);

echo json_encode($data); 
?> 
