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

		if (!current_user_can('administrator')) {
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
			$return .= "<h2>$name</h2>";
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

		return $return;

	} // end fitgoal_auth_func_dev
}

add_shortcode( 'cb_churn', array( 'CBChurnShortcode', 'shortcode' ) );

