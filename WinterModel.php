<?php

const LEVEL_WEIGHT = 0.2;

const TREND_WEIGHT = 0.01;

const SEASONAL_WEIGHT = 0.01;	
	
class WinterModel{

	private $int_seasonal_length = NULL;
	
	private $int_seasons = NULL;

	function __construct($arr_data_point = array()){
		
		$this->arr_input_data_point = $arr_data_point;

		$this->arr_data_point = array();
		
		$this->arr_buffer_level = array();
		
		$this->arr_buffer_trend = array();
		
		$this->arr_buffer_season = array();		
		
		$this->arr_buffer_future = array();		
		
	}
	
	function initialize(){
		
		if($this->int_seasonal_length == 0 ) $this->__computeSeasonalLength();		
		
		for($i=0; $i< $this->int_seasons; $i++ ){
			
			for($k=0; $k< $this->int_seasonal_length; $k++ ){
				
				$this->arr_data_point[$i][$k] = isset($this->arr_input_data_point[$i][$k]) ? $this->arr_input_data_point[$i][$k] : 0;
				
				$this->arr_buffer_level[$i][$k] = 0;
				
				$this->arr_buffer_trend[$i][$k] = 0;
				
				$this->arr_buffer_season[$i][$k] = 0;	
											
			}
			
		}
		
	}
	
	function __computeSeasonalLength(){
		
		$arr_length = array();
		
		$this->int_seasons = 0;
				 ;
		foreach($this->arr_input_data_point as $arr_values){
			
			array_push($arr_length,count($arr_values));
			
			$this->int_seasons++;
					
		}
		
		$this->int_seasonal_length = max($arr_length);
			
	}
	
	function __computeInitialTrend(){
		
		$trend_season_1 = isset($this->arr_data_point[0]) ? array_sum($this->arr_data_point[0]) : 0;
		
		$trend_season_2 = isset($this->arr_data_point[1]) ? array_sum($this->arr_data_point[1]) : 0;
		
		$this->arr_buffer_trend[0][0] = ($trend_season_2 - $trend_season_1) / $this->int_seasonal_length;		
	
	}

	function __computeInitialSeason(){
	
		foreach($this->arr_data_point as $key_season => $arr_period_data){
				
			$int_sum_period = array_sum($arr_period_data);
				
			foreach($arr_period_data as $key_period => $val_period ){
				
				if(isset($this->arr_data_point[$key_season][$key_period])){
				
					$this->arr_buffer_season[$key_season][$key_period] = round($this->arr_data_point[$key_season][$key_period]/$int_sum_period,4);
				
				}else{
				
					$this->arr_buffer_season[$key_season][$key_period] = 0;
					
				}
					
			}
		
		}
				
	
	}	
	
	function __computeInitialLevel(){
		
		$this->arr_buffer_level[0][0] =   round($this->arr_data_point[0][0]/$this->arr_buffer_season[0][0],4);
	
	}
	
	function __computeExponentialSmoothing($int_season,$int_period){
	
//	echo "__computeExponentialSmoothing $int_season :: $int_period <br/>";
	
		$val_expo_smooth = 0;

		$vals = 0;		
		
		for($int_season ;$int_season >=0;$int_season--){
			
			for($int_period ;$int_period >=0;$int_period--){
	 
				$tmp_step =	$this->arr_data_point[$int_season][$int_period] * LEVEL_WEIGHT;
				
				if($vals>0){
					
					for($i=0;$i<$vals;$i++){
						
						$tmp_step  = $tmp_step * (1 - LEVEL_WEIGHT);
						
					}
				
				}
				
				$val_expo_smooth += $tmp_step;	
				 
				$vals++;
								
			}
			
			$int_period = $this->int_seasonal_length - 1;
			
		}
		
		$tmp_step = $this->arr_buffer_future[0][0];
		
		for($i=0;$i<$vals;$i++){
			
			$tmp_step  = $tmp_step * (1 - LEVEL_WEIGHT);
			
		}
		
		$val_expo_smooth += $tmp_step;
		
		//echo " $val_expo_smooth <br/>";
		return $val_expo_smooth;
		
	}	
	
	function __computeStep($int_season , $int_period){
		
		//echo "computing $int_season :: $int_period <br/>";
		
		if($int_season == 0 && $int_period == 0){
			
			$this->arr_buffer_future[$int_season][$int_period] = $this->arr_data_point[$int_season][$int_period];
			
			return true;
			
		}
		 
		if($int_period == 0){
			
			$data_t1 = $this->arr_data_point[$int_season -1][$this->int_seasonal_length -1];					
			
			$level_t1 = $this->arr_buffer_level[$int_season -1][$this->int_seasonal_length -1];			
			
			$trend_t1 = $this->arr_buffer_trend[$int_season -1][$this->int_seasonal_length -1];					
			
		}else{
		
			$data_t1 = $this->arr_data_point[$int_season][$int_period-1];
			
			$level_t1 = $this->arr_buffer_level[$int_season][$int_period-1];
			
			$trend_t1 = $this->arr_buffer_trend[$int_season][$int_period-1];
			
			
		}
		
		if(!isset( $this->arr_data_point[$int_season][$int_period])){
			
			$int_step_back_season = $int_season;
			
			$int_step_back_period = $int_period;
			
			if($int_period == 0){
				
				$int_step_back_season = $int_season - 1;
				
				$int_step_back_period = $this->int_seasonal_length -1;
				
			}else{
			
				$int_step_back_period = $int_period - 1;
			
			}
			
			$this->arr_data_point[$int_season][$int_period] = $this->__computeExponentialSmoothing($int_step_back_season,$int_step_back_period);
			
		}
		
		$data_t2 = $this->arr_data_point[$int_season][$int_period];
		
		$int_next_season = $this->arr_buffer_season[$int_season][$int_period];
		
		//at+1
		$int_next_level = round(LEVEL_WEIGHT * ($data_t2 / $this->arr_buffer_season[$int_season][$int_period]) + (1 - LEVEL_WEIGHT) * ($level_t1 + $trend_t1),4);
		
		//bt+1
		$int_next_trend = round(TREND_WEIGHT * ($int_next_level - $level_t1) + (1 - TREND_WEIGHT) * ($trend_t1),4);
		
		//ct+p+1
		$int_next_period_season = round(SEASONAL_WEIGHT * ($data_t2 / $int_next_level) + (1 - SEASONAL_WEIGHT) * ($int_next_season),4);
		
		//ft+1
		$int_next_future = 	round( ($int_next_level + $int_next_trend ) * $int_next_season ,4 );
		
		
		$this->arr_buffer_level[$int_season][$int_period] = $int_next_level;
		
		$this->arr_buffer_trend[$int_season][$int_period] = $int_next_trend;
		
		$this->arr_buffer_future[$int_season][$int_period] = round($int_next_future,2);												

		//set the data point for the future values		
		if(!isset($this->arr_data_point[$int_season][$int_period])) $this->arr_data_point[$int_season][$int_period] = $this->arr_buffer_future[$int_season][$int_period];
		
	//	echo "computing $int_next_level :: $int_next_trend :: $int_next_period_season :: $int_next_future <br/>";
		
		//period ahead season					
		$this->arr_buffer_season[$int_season+1][$int_period] = $int_next_period_season;			
		
		return true;
		
	}
	
	function __computeFutureInformation($int_perdict_values){
		
		for($i=0; $i< $this->int_seasons; $i++ ){
			
			for($k=0; $k< $this->int_seasonal_length; $k++ ){
			
				$this->__computeStep($i, $k);
					
			}
			
		}
 		
		if($k >= $this->int_seasonal_length){
		
			$k=0 ;
			
		}
		
		while($int_perdict_values > 0 ){
			
			$this->__computeStep($i, $k);
			
			if($k == $this->int_seasonal_length - 1 || $k >= $this->int_seasonal_length){
				
				$k = 0 ;
				
				$i++;
				 
			}else{
				
				$k++;
				
				
			}
			
			$int_perdict_values--;
				
		}
		
	}
	
	function process($int_perdict_values = 0){
		
		$this->initialize();
		
		$this->__computeInitialTrend();
		
		$this->__computeInitialSeason();
		
		$this->__computeInitialLevel();
		
		$this->__computeFutureInformation($int_perdict_values);
		
		return $this->arr_buffer_future;
	}
	
	
}


$arr_data_point = array();

$arr_data_point[0] = array(19,20,21,22,23);

$arr_data_point[1] = array(20,21,22,23,24);

$arr_data_point[2] = array(21,22,23,24,25);

$obj_winter_model = new WinterModel($arr_data_point);

$arr_perdicted_values = $obj_winter_model->process(14);

$arr_finalized_data = array();

echo '<h2>Perdicated</h2>';

echo '<table width="300" border="1" cellspacing="2" cellpadding="2" style="background-color:#333333;">';

for($j=0;$j<5;$j++){
	
	echo '<tr>';
	
	for($i=0;$i<7;$i++){
		
		$int_val = isset($arr_data_point[$i][$j]) ? $arr_data_point[$i][$j] : (isset($arr_perdicted_values[$i][$j]) ? $arr_perdicted_values[$i][$j] : '') ;
		
		echo ' <td style="background-color:#FFFFFF" width="40">'.$int_val.'</td>';
		
	}
	
echo '</tr>';
	
	
}

echo '</table>';

echo '<h2>all Calculated future</h2>';

echo '<table width="300" border="1" cellspacing="2" cellpadding="2" style="background-color:#333333;">';

for($j=0;$j<5;$j++){
	
	echo '<tr>';
	
	for($i=0;$i<7;$i++){
		
		$int_val =  (isset($arr_perdicted_values[$i][$j]) ? $arr_perdicted_values[$i][$j] : '') ;
		
		echo ' <td style="background-color:#FFFFFF" width="40">'.$int_val.'</td>';
		
	}
	
echo '</tr>';
	
	
}

echo '</table>';
