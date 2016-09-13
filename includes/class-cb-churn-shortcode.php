<?php

/**
 * Churn calculation shortcode.
 *
 * Display churn information to admins.
 *
 * @since 1.0.0
 * @package challengebox
 */


class CBChurnShortcode {

	public static function shortcode( $atts, $content = "" ) {

		$a = shortcode_atts( array(
				'debug' => false,
		), $atts );

		if ($a['debug']) {
			error_reporting(E_ALL);
			ini_set('display_errors', true);
		}

		$return = "";

		if (!current_user_can('shop_manager') && !current_user_can('administrator')) {
			$return = 'You don\'t have permisison to view this page.';
			return $return;
		} 

		$churn_data = CBWoo::get_churn_data();

		// Render raw data
		$csv_rows = array();
		foreach ($churn_data->data as $user_id => $user_row) {
			$user_row["id"] = $user_id;
			$row = array();
			foreach ($churn_data->columns as $column) {
				if (isset($user_row[$column])) {
					$row[] = $user_row[$column];
				} else {
					$row[] = NULL;
				}
			}
			$csv_rows[] = $row;
		}
		$out = fopen('php://memory', 'w'); 
		fwrite($out, "data:text/csv;charset=utf-8,");
		fputcsv($out, $churn_data->columns);
		foreach ($csv_rows as $row) {
			fputcsv($out, $row);
		}
		$size = ftell($out);
		fseek($out, 0);
		$return .= '<script>var csv = '.json_encode(fread($out, $size)).';</script>';
		$return .= '<a class="pull-right" href="javascript:window.open(encodeURI(csv));">Raw data</a>';

		// Render rollups
		$rollups = CBWoo::get_churn_rollups($churn_data);

		$columns = array_merge(array('cohort'), $churn_data->months);
		foreach ($rollups as $name => $rollup) {
			$return .= "<h2>$name <a id=\"$name\" href=\"#$name\">&para;</a></h2>";
			$return .= '<table class="table-striped">';

			$return .= "<tr>";
			foreach ($columns as $column) {
				$return .= "<th>$column</th>";
			}
			$return .= "</tr>";

			foreach ($rollup as $row) {
				$return .= "<tr>";
				foreach ($columns as $column) {
					if ('cohort' == $column) {
						$return .= "<th>" . $row[$column] . "</th>";
					} else {
						$return .= "<td>" . number_format($row[$column]) . "</td>";
					}
				}
				$return .= "</tr>";
			}

			$return .= "</table>";
		}

		// Box numbers
		//$subscriptions = get_transient('cb_export_subscriptions_raw');
		$boxes = get_transient('cb_export_boxes_raw');

		$box_rollup = array();
		foreach (array('created', 'shipped') as $cohort_type) {
			$box_rollup[$cohort_type] = array(
				'm1' => array('cohort' => 'm1'),
				'm2' => array('cohort' => 'm2'),
				'm3' => array('cohort' => 'm3'),
				'm4' => array('cohort' => 'm4'),
				'm5' => array('cohort' => 'm5'),
				'm6' => array('cohort' => 'm6'),
				'm7' => array('cohort' => 'm7'),
				'm8' => array('cohort' => 'm8'),
				'total' => array('cohort' => 'total'),
			);
			foreach ($boxes as $box) {
				$month = $box['month'];
				if ('m1' != $month) {
					$month = 'm' . $month;
				}
				$column = $cohort_type . '_cohort';
				$cohort = $box[$column];
				if (!isset($box_rollup[$cohort_type][$month][$cohort])) {
					$box_rollup[$cohort_type][$month][$cohort] = 0;
				}
				if (!isset($box_rollup[$cohort_type]['total'][$cohort])) {
					$box_rollup[$cohort_type]['total'][$cohort] = 0;
				}
				$box_rollup[$cohort_type][$month][$cohort] += 1;
				$box_rollup[$cohort_type]['total'][$cohort] += 1;
			}
		}
		foreach ($box_rollup as $name => $rollup) {
			$return .= "<h2>boxes $name <a id=\"$name\" href=\"#$name\">&para;</a></h2>";
			$return .= '<table class="table-striped">';

			$return .= "<tr>";
			foreach ($columns as $column) {
				$return .= "<th>$column</th>";
			}
			$return .= "</tr>";

			foreach ($rollup as $row) {
				$return .= "<tr>";
				foreach ($columns as $column) {
					if (!isset($row[$column])) $row[$column] = 0;
					if ('cohort' == $column) {
						$return .= "<th>" . $row[$column] . "</th>";
					} else {
						$return .= "<td>" . number_format($row[$column]) . "</td>";
					}
				}
				$return .= "</tr>";
			}

			$return .= "</table>";
		}

		return $return;

	} // end fitgoal_auth_func_dev


	/**
	 * Revenue shortcode.
	 */
	public static function revenue_shortcode( $atts, $content = "" ) {

		if ($_GET['debug']) {
			error_reporting(E_ALL);
			ini_set('display_errors', true);
		}

		$return = "";

		if (!current_user_can('shop_manager') && !current_user_can('administrator')) {
			$return = 'You don\'t have permisison to view this page.';
			return $return;
		} 

		$revenue_data = CBWoo::get_revenue_data();

		foreach ($revenue_data as $table) {

			// Heading
			$return .= "<h2>$table->title</h2>";
			$return .= '<table class="table table-smaller table-striped">';

			// Table head
			$return .= '<tr>';
			foreach (current($table->data) as $key => $row) {
				$trend = array_values(array_filter(array_map(function ($row) use ($key) { return $row->$key; }, $table->data), 'is_numeric'));
				$trend_numbers = implode(',', $trend);
				$key = str_replace('boxes', '📦', $key);
				$key = str_replace('box', '📦', $key);
				$key = str_replace('users', '💁', $key);
				$key = str_replace('user', '💁', $key);
				$key = str_replace('calendar', '📅', $key);
				if (sizeof($trend)) $return .= "<th><nobr>$key</nobr> <span class=\"spark-line\">$trend_numbers</span></th>";
				else $return .= "<th><nobr>$key</nobr></th>";
			}
			$return .= '</tr>';

			// Table body
			foreach ($table->data as $row) {
					$return .= '<tr>';
					foreach ($row as $k => $datum) {
						if (is_numeric($datum)) {
							$return .= '<td>'.number_format($datum).'</td>';
						} else {
							$return .= '<td>'.$datum.'</td>';
						}
					}
					$return .= '</tr>';
			}
			$return .= '</table>';

			$query_id = uniqid();
			$return .= "<a class=\"pull-right\" style=\"margin-top: -20px;\" onclick=\"jQuery('#$query_id').toggle();\">show query</a>";
			$return .= "<pre id=\"$query_id\" style=\"display: none; font-size: 10px;\">$table->query</pre>";

		}

		return $return;
	}
}

add_shortcode( 'cb_churn', array( 'CBChurnShortcode', 'shortcode' ) );
add_shortcode( 'cb_revenue', array( 'CBChurnShortcode', 'revenue_shortcode' ) );

