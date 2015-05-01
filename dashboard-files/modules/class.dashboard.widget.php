<?php

if( ! class_exists( 'CustomDashboardWidget' ) ){

	class CustomDashboardWidget extends AdminDashboard{

		public $plugin_url;
	    public $plugin_dir;
		public $version;
		public $options;
		public $dash_widgets;
		public $widget_table;

		public function __construct() {
			global $adminDashboard, $wpdb;;
			//parent::__construct();

			if( $adminDashboard ){
				$this->plugin_dir = $adminDashboard->plugin_dir;
		        $this->plugin_url = $adminDashboard->plugin_url;
				$this->version = $adminDashboard->version;
				$this->options = $adminDashboard->options;
			}
			$this->widget_table = $wpdb->prefix . 'widgets_table';
			//$this->dash_widgets = $this->get_dash_widgets();
			add_action('init', array($this, 'get_dash_widgets'));
			add_filter( 'dashboard_form', array( $this, 'dashboard_widget_style_form' ), 15 );
			add_action( 'plg_tables_installed', array( $this, 'menu_tables_install' ), 14 );
			add_action( 'save_dashboard_data', array( $this, 'save_widget_data' ), 14  );
			add_action( 'admin_init', array( $this, 'setup_dashboard_widget' ) );
		}

		public function menu_tables_install() {
			global $wpdb;
			global $jal_db_version;

			$sql = "CREATE TABLE IF NOT EXISTS " . $this->widget_table . " (
				id INT(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
				widget_title VARCHAR(255) NOT NULL,
				widget_content TEXT
				);";

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
			add_option( "jal_db_version", $jal_db_version );
		}

		public function setup_dashboard_widget() {
			$this->dash_widgets = $this->get_dash_widgets();
			foreach( $this->dash_widgets as $widget ){
				$content = $widget['widget_content'];
				$title = $widget['widget_title'];
				add_action( 'wp_dashboard_setup', function() use($title, $content ) {
					wp_add_dashboard_widget(
		                 str_replace( ' ', '_', $title ),         // Widget slug.
		                 $title,         // Title.
		                 function() use($content)  {
		                 	echo stripslashes_deep( $content );
		                 }
		        	);
				} );
			}
		}

		public function save_widget_data( $data ) {
			global $wpdb;
			if( isset( $_POST['dashboard_nonce_field'] ) && wp_verify_nonce( $_POST['dashboard_nonce_field'], 'dashboard_nonce_action' ) ) {
				if( $_POST['dash_widget_title'] != '' && $_POST['dash_widget_content'] != '' ) {
					if( $_POST['widget_id'] == '' )
						$q = $wpdb->insert( $this->widget_table, array( 'widget_title'=>$_POST['dash_widget_title'], 'widget_content'=>$_POST['dash_widget_content'] ) );
					else {
						$wpdb->update( $this->widget_table, array( 'widget_title'=>$_POST['dash_widget_title'], 'widget_content'=>$_POST['dash_widget_content'] ), array( 'id'=>$_POST['widget_id'] ) );
					}
					wp_redirect( admin_url( 'admin.php?page=dashboard-settings&msg=' . __( 'Settings+saved!', 'cdw' ) ) );
					exit;
				}
			}

			if( isset( $_GET['dashboard_nonce_field'] ) && wp_verify_nonce( $_GET['dashboard_nonce_field'], 'dashboard_nonce_action' ) ) {
				if( isset( $_GET['action'] ) && isset( $_GET['id'] ) && $_GET['action'] == 'delete_widget' ) {
					$wpdb->delete( $this->widget_table, array( 'id' => sprintf( '%d', $_GET['id'] ) ) );
					wp_redirect( admin_url( 'admin.php?page=dashboard-settings&msg=' . __( 'Admin+widget+deleted!', 'cdw' ) ) );
					exit;
				}
			}
		}

		public function get_dash_widgets() {
			global $wpdb;
			$sql = "SELECT * from " . $this->widget_table;
			return $wpdb->get_results( $sql, 'ARRAY_A' );
		}

		public function get_dash_widget_by_id( $id ) {
			global $wpdb;
			$sql = $wpdb->prepare( "SELECT * from " . $this->widget_table . " where id = '%s' ", $id );
			return $wpdb->get_results( $sql, 'ARRAY_A' );
		}

		public function dashboard_widget_style_form( $form ) {
			ob_start();
			?>
			<div class="postbox dg_ap_box">
				<h3 class="hndle"><span><?php _e( 'Dashboard Widget', 'cdw' ) ?></span></h3>
				<div class="inside">
					<div style="width:100%; padding-top: 10px;">
                        <strong><?php _e( 'Create new Dashboard Widget', 'cdw' ) ?></strong>
                    </div>
                    <?php
						$edit = false;
						if( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'edit_widget' ) {
							$widget = $this->get_dash_widget_by_id( $_REQUEST['id'] );
							$edit = true;
						}
					?>
					<div class="clear"></div>
					<div style="width:100%; padding-top: 10px;">
                        <strong><?php _e( 'Widget Title', 'cdw' ) ?></strong><br>
                        <input type="text" name="dash_widget_title" size="60" value="<?php echo $edit ? $widget[0]['widget_title'] : ''; ?>" />
                    </div>
                    <div class="clear"></div>
                    <div style="width:100%; padding-top: 10px;">
                        <strong><?php _e( 'Widget Content - HTML allowed', 'cdw' ) ?></strong><br>
                        <?php wp_editor( $edit ? stripslashes_deep( $widget[0]['widget_content'] ) : '', 'dash_widget_content' ); ?>
						<input type="hidden" name="widget_id" value="<?php echo $edit ? $widget[0]['id'] : ''; ?>" />
                    </div>
                    <div class="clear"></div>

					<table class="form-table">
						<?php
							$dash_widget_titles = explode( ',', get_option( 'dash_widget_title' ) );
							$dash_widget_contents = explode( ',', get_option( 'dash_widget_content' ) );
						?>
						<tr>
							<th><?php _e( 'Created Widgets', 'cdw' ) ?></th>
							<td>
								<?php foreach( $this->dash_widgets as $widget ){ ?>
								<div class="widget_list">
									<?php echo isset($widget['widget_title']) ? $widget['widget_title'] : '' ?>
									<div>
										<a href="<?php echo admin_url( 'admin.php?page=dashboard-settings&action=edit_widget&id=' . $widget['id'] ) ?>"><i class="fa fa-edit"></i></a>
										 |
										<a href="<?php echo admin_url( 'admin.php?page=dashboard-settings&noheader=true&action=delete_widget&id=' . $widget['id'] . '&dashboard_nonce_field=' . wp_create_nonce( 'dashboard_nonce_action' ) ) ?>"><i class="fa fa-times"></i></a>
									</div>
								</div>
								<?php } ?>
							</td>
						</tr>
					</table>
					<div class="clear"></div>
				</div>
			</div>
			<?php
			$output = $form . ob_get_contents();
			ob_end_clean();
			return $output;
		}

	}

	new CustomDashboardWidget();
}
