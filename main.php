<?php
date_default_timezone_set('America/Sao_Paulo');

$config = readConfig();
$info = rollConfig($config);
$toUse = verifyStatus($info);
$toExecute = analiseSchedule($toUse);
executeThings($toExecute);






function executeThings($toExecute){
    // status :
    // 0 = nao hella
    // 1 = n existe
    // 2 = stop
    // 3 = running
    foreach ($toExecute as $key => $value) {
        if( $value['actionToDo']){
            if($value['status'] == 1){
                $comand = '/usr/local/bin/aws rds create-db-instance'
                .' --db-cluster-identifier '.$value['DBClusterIdentifier']
                .' --db-instance-identifier '.$value['Name']
                .' --db-instance-class '.$value['DBInstanceClass']
                .' --engine '.$value['Engine'];
            }elseif($value['status'] == 2){
                $comand = '/usr/local/bin/aws rds start-db-instance'
                .' --db-instance-identifier '.$value['Name'];
            }
        }else{
            if($value['status'] == 2 or $value['status'] == 3){
                $comand = '/usr/local/bin/aws rds delete-db-instance'
                .' --db-instance-identifier '.$value['Name'];
            }
        }
        if(isset($comand)){
            echo $comand;
            $output = shell_exec($comand);
            echo $output;
        }
    }
}


function analiseSchedule($infos){
    $output = [];
    foreach ($infos as $key => $value) {
        if($value["status"] != 0){
            $timeOn = false;
            $dayOn = false;
            foreach ($value["Cicle"] as $key2 => $cicle) {
                // resolvendo dida 
                if($cicle["Option"][0] == "working week"){
                    $days = [1,2,3,4,5];
                    if(in_array(date('w'), $days)){
                        $dayOn = true; 
                    }
                }
                else{
                    if(in_array(date('w'), $cicle["Option"])){
                        $dayOn = true; 
                    }
                }

                if($dayOn == true){
                    $startTime = (int)str_replace(':','',$cicle["Create"]);
                    $endTime = (int)str_replace(':','',$cicle["Delete"]);
                    $now = (int)date('Hi');
                    var_dump($now >= $startTime and $now <= $endTime);
                    var_dump($now, $startTime,$endTime);
                    if($now >= $startTime and $now <= $endTime ){
                        $value["actionToDo"] = true;
                        $timeOn = true;
                        $output[$key] = $value;
                    }else{
                        if( $timeOn != true){
                            $value["actionToDo"] = false;
                            $output[$key] = $value;
                        }
                    }
                }

            }
        }
        
    }
    return $output;
}




function verifyStatus($info){
    $output = [];
    foreach ($info as $key => $instances) {
        $instances['config']['status'] = null;
        $instanceChaking[] = array();
        foreach ($instances['status']["DBInstances"] as $key => $status) {
            if($status['DBInstanceIdentifier'] ==$instances['config']['Name'] ){
                $instances['config']['status'] = $status['DBInstanceStatus'];
            }
        }
        if($instances['config']['status'] == null){
            $instances['config']['status'] = 1;
        }elseif($instances['config']['status']=='stopped'){
            $instances['config']['status'] = 2;
        }
        elseif($instances['config']['status']=='available'){
            $instances['config']['status'] = 3;
        }
        else{
            $instances['config']['status'] = 0;
        }
        $output[] = $instances['config'];
    }
    return $output;
}

function rollConfig($config){
    $infos = [];
    foreach ($config as $key => $instance) {
        $instancesRunning = getRunningInstances($instance['Profile']);
        $infos[] = [
            'config' => $instance,
            'status' => $instancesRunning 
        ];
    }
    return $infos;
}

function getRunningInstances($profile){
    $comand = "/usr/local/bin/aws rds describe-db-instances";
    if($profile != "Default"){
        $comand_sufix = " --profile ".$profile;    
    }else{
        $comand_sufix = "";
    }
    $instances = shell_exec($comand.$comand_sufix);
    $instances = json_decode($instances,true);
    if($instances == null){
        error("erro ao pegar instancias");
    }
    return $instances;
}


function readConfig(){
    // lendo scale config
    $myfile = fopen(dirname(__FILE__)."/scaleConfig.json", "r") or die("Erro ao encontrar config.json");
    $scaleConfig = fread($myfile,filesize(dirname(__FILE__)."/scaleConfig.json"));
    fclose($myfile);
    
    $scaleConfig = json_decode($scaleConfig,true);
    if($scaleConfig!= null){
        return $scaleConfig;
    }
    else{
        error('configuracao nao encontrada/errada');
    }
}

function error($msg){
    die($msg);
}





