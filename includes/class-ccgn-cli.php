<?php

class CCGN_cli {
  /**
   * Move the account data to a new one
   * WARNING! please make sure the user IDs are correct before executing this command
   * it replaces referenced user IDs and this could cause
   * several errors with user accounts
   * 
   * ## OPTIONS
   * --from=<original user id>
   * The original user ID is the old account ID or the one we're going to gather the
   * data in order to move it to another
   * 
   * --to=<target user id>
   * The new account user id where the data is going to move
   */
  function move_account( $args, $assoc_args) {
    if (!empty($assoc_args['from'] && !empty($assoc_args['to']))) {
      WP_CLI::log('Moving data from user ID:  '. $assoc_args['from'] .' to'. $assoc_args['to']);
      $this->link_old_entries( $assoc_args );
      //set user type
      $this->set_user_type( $assoc_args );
      //set user_roles
      $this->set_user_roles( $assoc_args );
      $this->switch_nicenames( $assoc_args );
      ccgn_create_profile( intval( $assoc_args['to'] ) );

      _ccgn_registration_user_set_stage( intval( $assoc_args['to'] ), CCGN_APPLICATION_STATE_ACCEPTED );
      
      WP_CLI::success( 'Profile moved' );

    } else {
      WP_CLI::error( 'You need to set --from and --to parameters' );
    }
  }
  private function link_old_entries( $assoc_args ) {
    //refered as a voucher on position 1
      ccgn_link_refering_entries_to_new_user( intval( $assoc_args['from'] ),"Choose Vouchers", 1, intval( $assoc_args['to'] ) );
      //refered as a voucher on position 2
      ccgn_link_refering_entries_to_new_user( intval( $assoc_args['from'] ),"Choose Vouchers", 2, intval( $assoc_args['to'] ) );
      ccgn_link_refering_entries_to_new_user( intval( $assoc_args['from'] ),"Vouch for Applicant", 7, intval( $assoc_args['to'] ) );
      ccgn_link_refering_entries_to_new_user( intval( $assoc_args['from'] ),"Vote on Membership", 4, intval( $assoc_args['to'] ) );
      //if we pass a number to the form name, we get all the matching fields from all forms
      ccgn_link_entries_by_user_to_new_user( intval( $assoc_args['from'] ), 0, intval( $assoc_args['to'] ) );
  }
  private function switch_nicenames( $assoc_args ) {
    $current_nicename = get_userdata( intval( $assoc_args['from'] ) )->user_nicename;
    $update_old_user = wp_update_user( array( 'ID' => intval( $assoc_args[ 'from' ] ), 'user_nicename' => $current_nicename.'-'.wp_generate_password(6,false) ) );
    $update_new_user = wp_update_user( array( 'ID' => intval( $assoc_args[ 'to' ] ), 'user_nicename' => $current_nicename ) );
    if ( !is_wp_error( $update_new_user ) ) {
      WP_CLI::success( 'Nicename updated' );
    } else {
      WP_CLI::error( "Couldn't update nicename" );
    }
  }
  private function set_user_roles( $assoc_args ) {
    $current_roles = get_userdata( intval( $assoc_args['from'] ) )->roles;
    $new_user_roles = get_userdata( intval( $assoc_args['to'] ) )->roles;
    $new_user = new WP_User( intval( $assoc_args['to'] ) );
    foreach ($new_user_roles as $role) {
      $new_user->remove_role( $role );
    }
    foreach ( $current_roles as $role ) {
      $new_user->add_role( $role );
    }
    $new_roles = get_userdata( intval( $assoc_args['to'] ) )->roles;
    if ( $new_roles === $current_roles ) {
      WP_CLI::success( 'Roles updated' );
    } else {
      WP_CLI::error( "Couldn't update roles" );
    }
  }
  private function set_user_type( $assoc_args ) {
    $origin_user_type = ccgn_applicant_type_desc( intval( $assoc_args['from'] ) );
    if ( $origin_user_type == 'Individual' ) {
      ccgn_user_set_individual_applicant( intval( $assoc_args['to'] ) );
      ccgn_user_level_set_member_individual( intval( $assoc_args['to'] ) );
    } else if ( $origin_user_type == 'Institution' ) {
      ccgn_user_set_institutional_applicant( intval( $assoc_args['to'] ) );
      ccgn_user_level_set_member_institution( intval( $assoc_args['to'] ) );
    }
  }
}

function ccgn_cli_register_commands() {
  WP_CLI::add_command( 'ccgn', 'CCGN_cli' );
}
add_action( 'cli_init', 'ccgn_cli_register_commands' );