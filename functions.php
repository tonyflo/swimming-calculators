<?php
/**
 * Theme functions and definitions
 *
 * @package HelloElementorChild
 */

/**
 * Load child theme css and optional scripts
 *
 * @return void
 */
function hello_elementor_child_enqueue_scripts() {
	wp_enqueue_style(
		'hello-elementor-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		[
			'hello-elementor-theme-style',
		],
		'1.0.0'
	);
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_enqueue_scripts', 20 );

// for lcm, first column men, second column women
// assumes that from or to is scy
function get_conversion_factor($event, $gender, $from, $to) {
	$factor = 1;
	$lcm = array(
		'50-free' => array(0.860, 0.871),
		'100-free' => array(0.863, 0.874),
		'200-free' => array(0.865, 0.874),
		'400-free' => array(1.105, 1.112),
		'800-free' => array(1.105, 1.120),
		'1500-free' => array(0.965, 0.975),
		'100-fly' => array(0.868, 0.877),
		'200-fly' => array(0.866, 0.881),
		'100-back' => array(0.835, 0.853),
		'200-back' => array(0.849, 0.857),
		'100-breast' => array(0.856, 0.870),
		'200-breast' => array(0.858, 0.878),
		'200-IM' => array(0.857, 0.867),
		'400-IM' => array(0.865, 0.876),
		'200-free-relay' => array(0.860, 0.871),
		'400-free-relay' => array(0.863, 0.874),
		'800-free-relay' => array(0.867, 0.874),
		'200-medley-relay' => array(0.858, 0.869),
		'400-medley-relay' => array(0.856, 0.868),
	);
	$scm = [
		'400-free' => 1.143,
		'800-free' => 1.143,
		'1500-free' => 1.003,
	];
	
	if ($to == $from) {
		$factor = 1;
	} elseif($from == 'lcm' or $to == 'lcm') {
		// lcm
		$factor = $lcm[$event][get_gender($gender)];
	} else {
		// scm
		$factor = 0.896;
		if($event == '400-free' or $event == '800-free' or $event == '1500-free') {
			$factor = $scm[$event];
		}
	}
	
	if($to != 'scy') {
		$factor = 1/$factor;
	}
	
	return $factor;
}

// sometimes we need to convert to yards first
function calculate_conversion_factor($event, $gender, $from, $to) {
	if($to == 'scy' or $from == 'scy' or $to == $from) {
		// only need to convert once
		return get_conversion_factor($event, $gender, $from, $to);
	} elseif($to == 'scm') {
		// lcm to scm
		$lcm_to_scy = get_conversion_factor($event, $gender, $from, 'scy');
		$scy_to_scm = get_conversion_factor($event, $gender, 'scy', $to);
		return $lcm_to_scy * $scy_to_scm;
	} else {
		// scm to lcm
		$scm_to_scy = get_conversion_factor($event, $gender, $from, 'scy');
		$scy_to_lcm = get_conversion_factor($event, $gender, 'scy', $to);
		return $scm_to_scy * $scy_to_lcm;
	}
}

function get_gender($gender) {
	return ($gender == 'male' ? 0 : 1);
}

function time_to_string($m, $s, $d) {
	return sprintf("%s:%s.%s", str_pad($m, 2, '0', STR_PAD_LEFT), str_pad($s, 2, '0', STR_PAD_LEFT), str_pad($d, 2, '0', STR_PAD_LEFT));
}

function format_time($output) {
	$mm = floor($output / 60);
	$ss = floor($output % 60);
	$dd = floor(($output - floor($output)) * 100);
	return time_to_string($mm, $ss, $dd);
}

function convert_swim_time($fields) {
	$event = $fields['event'];
	$gender = $fields['gender'];
	$from = $fields['from'];
	$to = $fields['to'];

	$factor = calculate_conversion_factor($event, $gender, $from, $to);
	
	$min = $fields['min'];
	$sec = $fields['sec'];
	$dec = $fields['dec'];
	
	$input = ($min * 60) + $sec + ($dec / 100);
	$output = $input * $factor;
	
	return format_time($output);
}

function gender_output($gender, $event) {
	$output = sprintf("A %s", $gender);
	if (strpos($event, 'relay') !== false) {
    	$output = ($gender == "male" ? "Men" : "Women");
	}
	return $output;
}

function event_output($event) {
	$output = $event;
	if($event == '800-free') {
		$output = '800/1,000 free';
	} elseif( $event == '1500-free') {
		$output = '1,500/1,650 free';
	}
	return str_replace("-", " ", $output);
}

function get_description($fields) {
	$time = time_to_string($fields['min'], $fields['sec'], $fields['dec']);
	$event = event_output($fields['event']);
	$gender = gender_output($fields['gender'], $fields['event']);
	
	$text = sprintf("%s swimming the %s in a %s pool with a time of %s converts to a %s time of:", $gender, $event, strtoupper($fields['from']), $time, strtoupper($fields['to']));
	return $text;
}

add_action( 'elementor_pro/forms/new_record', function( $record, $ajax_handler ) {
	
	$raw_fields = $record->get( 'fields' );
	$fields = [];
	foreach ( $raw_fields as $id => $field ) {
		$fields[ $id ] = $field['value'];
	}

	$output['time'] = convert_swim_time($fields);
	$output['description'] = get_description($fields);
		
	$ajax_handler->add_response_data( true, $output );
}, 10, 2);