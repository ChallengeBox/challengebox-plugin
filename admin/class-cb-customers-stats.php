<?php
/**
 * Adds an admin page which has Export Customers to CSV functionality
 * Admin page also shows customer #, and can be extended to show other stats
 * like monthly breakdown
 * 
 * Code modified from -  http://wordpress.org/extend/plugins/export-users-to-csv/
 *
 * @package    ChallengeBox
 * @subpackage ChallengeBox/admin
 * @version 1.0.0
 * @since 0.1
 * @author Alex Shapiro <shapiro.alex@gmail.com>
 */

class CBCustomersStats {

	/**
	 * Class contructor
	 *
	 * @since 0.1
	 **/
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
		add_action( 'init', array( $this, 'generate_csv_segment' ) );
		add_filter( 'pp_eu_exclude_data', array( $this, 'exclude_data' ) );
	}

	/**
	 * Add administration menus
	 *
	 * @since 0.1
	 **/
	public function add_admin_pages() {
		add_users_page( __( 'Export to CSV', 'export-users-to-csv' ), __( 'Export to CSV', 'export-users-to-csv' ), 'list_users', 'export-users-to-csv', array( $this, 'users_page' ) );
	}

	public function generate_csv_segment() {
		if ( isset( $_POST['_wpnonce-pp-eu-export-users-users-page_export'] ) ) {
			check_admin_referer( 'pp-eu-export-users-users-page_export', '_wpnonce-pp-eu-export-users-users-page_export' );

			error_reporting(E_ALL);
			ini_set('display_errors', true);
			header('Content-Type: text/csv; utf-8');
			header("Content-Disposition: attachment; filename=users.csv");
			header("Pragma: no-cache");
			header("Expires: 0");

			$rows = array();
			$keys = array();

			foreach (get_users() as $user) {
				$customer = new CBCustomer($user->ID);
				$row = array_merge(
					array(
						'id' => $user->ID,
						'email' => $user->user_email,
						'registered' => $user->user_registered,
						'first_name' => $user->user_firstname,
						'last_name' => $user->user_lastname,
					),
					$customer->get_segment_data()
				);
				$keys = array_unique(array_merge($keys, array_keys($row)));
				$rows[] = $row;
			}

			// Header row
			$data = [];
			foreach ($keys as $key) {
				$data[] = '"' . str_replace( '"', '""', $key ) . '"';
			}
			echo implode( ',', $data ) . "\n";

			// Data rows
			foreach ($rows as $row) {
				$data = [];
				foreach ($keys as $key) {
					$data[] = '"' . str_replace( '"', '""', $row[$key] ) . '"';
				}
				echo implode( ',', $data ) . "\n";
			}

			exit;
		}
	}

	public function generate_csv_new() {

		global $wpdb;
		if ( isset( $_POST['_wpnonce-pp-eu-export-users-users-page_export'] ) ) {
			check_admin_referer( 'pp-eu-export-users-users-page_export', '_wpnonce-pp-eu-export-users-users-page_export' );

			$users = $wpdb->get_results( "
				select 
				    id,
				    user_registered,
				    user_email,
				    case
				        when A.meta_value = '' then 0
				        else 1
				    end
				    as active,
				    B.meta_value as month,
				    C.meta_value as gender,
				    D.meta_value as tshirt_size
				from 
				    wp_users U
				left join 
				    wp_usermeta A on A.user_id = U.id
				left join 
				    wp_usermeta B on B.user_id = U.id
				left join 
				    wp_usermeta C on C.user_id = U.id
				left join 
				    wp_usermeta D on D.user_id = U.id
				where
				    A.meta_key = 'active_subscriber'
				and    B.meta_key = 'box_month_of_latest_order'
				and C.meta_key = 'clothing_gender'
				and D.meta_key = 'tshirt_size'
				order by 
				    month desc, user_registered;
			" );

			$filename = 'CB_Customers_' . date( 'Y-m-d-H-i-s' ) . '.csv';		

			header( 'Content-Description: File Transfer' );
			header( 'Content-Disposition: attachment; filename=' . $filename );
			header( 'Content-Type: text/csv; charset=' . get_option( 'blog_charset' ), true );

			foreach ( $users as $user ) {
				$data = [];
				foreach ($user as $key => $value) {
					$data[] = '"' . str_replace( '"', '""', $value ) . '"';
				}

				echo implode( ',', $data ) . "\n";
			}

			exit;

		    //$exclude_data = apply_filters( 'pp_eu_exclude_data', array() );

		}
	}

	private function get_active_user_count() {
		global $wpdb; 
		$users = $wpdb->get_results( "
			select 
			    id		   
			from 
			    wp_users U
			left join 
			    wp_usermeta A on A.user_id = U.id
			where
			    A.meta_key = 'active_subscriber'
				AND
			    A.meta_value = 1
		" );
		return count($users);
	}
	
    /*  
	public function exclude_data() {
		$exclude = array( 'user_pass', 'user_activation_key' );

		return $exclude;
	}
	
	private function export_date_options() {
		global $wpdb, $wp_locale;

		$months = $wpdb->get_results( "
			SELECT DISTINCT YEAR( user_registered ) AS year, MONTH( user_registered ) AS month
			FROM $wpdb->users
			ORDER BY user_registered DESC
		" );

		$month_count = count( $months );
		if ( !$month_count || ( 1 == $month_count && 0 == $months[0]->month ) )
			return;

		foreach ( $months as $date ) {
			if ( 0 == $date->year )
				continue;

			$month = zeroise( $date->month, 2 );
			echo '<option value="' . $date->year . '-' . $month . '">' . $wp_locale->get_month( $month ) . ' ' . $date->year . '</option>';
		}
	}
    */



	/**
	 * Content of the settings page
	 *
	 * @since 0.1
	 **/
	public function users_page() {
		if ( ! current_user_can( 'list_users' ) )
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'export-users-to-csv' ) );
?>

<div class="wrap">
	<h2><?php _e( 'Export '. $this->get_active_user_count().' active customers to a CSV file', 'export-users-to-csv' ); ?></h2>
	<?php
	if ( isset( $_GET['error'] ) ) {
		echo '<div class="updated"><p><strong>' . __( 'No user found.', 'export-users-to-csv' ) . '</strong></p></div>';
	}
	?>
	<form method="post" action="" enctype="multipart/form-data">
		<?php wp_nonce_field( 'pp-eu-export-users-users-page_export', '_wpnonce-pp-eu-export-users-users-page_export' ); ?>
		<?php 
		/*		
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><label for"pp_eu_users_role"><?php _e( 'Role', 'export-users-to-csv' ); ?></label></th>
				<td>
					<select name="role" id="pp_eu_users_role">
						<?php
						echo '<option value="">' . __( 'Every Role', 'export-users-to-csv' ) . '</option>';
						global $wp_roles;
						foreach ( $wp_roles->role_names as $role => $name ) {
							echo "\n\t<option value='" . esc_attr( $role ) . "'>$name</option>";
						}
						?>
					</select>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label><?php _e( 'Date range', 'export-users-to-csv' ); ?></label></th>
				<td>
					<select name="start_date" id="pp_eu_users_start_date">
						<option value="0"><?php _e( 'Start Date', 'export-users-to-csv' ); ?></option>
						<?php $this->export_date_options(); ?>
					</select>
					<select name="end_date" id="pp_eu_users_end_date">
						<option value="0"><?php _e( 'End Date', 'export-users-to-csv' ); ?></option>
						<?php $this->export_date_options(); ?>
					</select>
				</td>
			</tr>
		</table>
		*/ 
		?>

		<p class="submit">
			<input type="hidden" name="_wp_http_referer" value="<?php echo $_SERVER['REQUEST_URI'] ?>" />
			<input type="submit" class="button-primary" value="<?php _e( 'Export', 'export-users-to-csv' ); ?>" />
		</p>
	</form>
<?php
	}

}

new CBCustomersStats;
