<?php

class CCGN_cli {
  /**
   * Get a list of vouchers from a user
   * 
   * Usage: wp ccgn get_vouchers <user ID>
   */
  function get_vouchers( $args, $assoc_args ) {
    if ( !empty( $args[0] ) ) {
      if ( $this->does_user_exist( $args[0] )) {
        $vouchers = ccgn_application_vouches( $args[0] );
        $requested_user = get_userdata( intval( $args[0] ) );
        WP_CLI::line( WP_CLI::colorize( '%8# Vouchers from: '.$requested_user->display_name.' %n' ) );
        WP_CLI::line('');
        foreach( $vouchers as $voucher ) {
          $voucher_user = get_userdata( $voucher['created_by'] );
          WP_CLI::line( WP_CLI::colorize('%Y>> Vouch from: '. $voucher_user->display_name) );
          WP_CLI::line( WP_CLI::colorize( '%MVouch ID: '.$voucher['id'].'%n' ) );
          WP_CLI::line('Answer: '.$voucher[3]);
          if ( !empty( $voucher[4] ) ) {
            WP_CLI::line( 'Reason:' );
            WP_CLI::line( $voucher[4] );
          }
          if ( !empty( $voucher[9] ) ) {
            WP_CLI::line( 'Reason:' );
            WP_CLI::line( $voucher[9] );
          }
          WP_CLI::line('------------------------');
          WP_CLI::line('');
        }
      } else {
        WP_CLI::error( 'Invalid User ID' );  
      }
    } else {
      WP_CLI::error( 'No user ID specified: Use: wp ccgn get_vouchers <user ID>' );
    }
  }
  /**
   * Sets the status of a user
   * the status should exist in the list of allowed statuses.
   * you can see that list here: https://wikijs.creativecommons.org/tech/websites/ccgn-development#user-status
   * 
   * Usage: wp ccgn set_status <user ID> <new status>
   */
  function set_status( $args, $assoc_args ) {
    if ( !empty( $args[0] ) && !empty( $args[1] ) ) {
      $this->set_user_status( intval( $args[0] ), $args[1] );
    } else {
      WP_CLI::error( 'No user or status specified. Use: wp ccgn set_status <user ID> <status>' );
    }
  }

  private function set_user_status( $user_id, $status ) {
    $allowed_status = array(
      'charter-form',
      'details-form',
      'vouchers-form',
      'received',
      'vouching',
      'legal',
      'update-vouchers',
      'update-details',
      'rejected',
      'accepted',
      'on-hold',
      'rejected-because-didnt-update-vouchers',
      'to-be-reviewed',
      'to-be-deleted'
    );
    if ( in_array( $status, $allowed_status ) ) {
      _ccgn_registration_user_set_stage( $user_id, $status );
    } else {
      WP_CLI::error( 'User status not accepted. See a list of accepted status here: https://wikijs.creativecommons.org/tech/websites/ccgn-development#user-status' );
    }
  }
  /**
   * Copy the Buddypress xProfile fields from one account to another
   *
   * ## OPTIONS
   * --from=<original user id>
   * The original user ID is the old account ID or the one we're going to gather the
   * data in order to move it to another
   * 
   * --to=<target user id>
   * The new account user id where the data is going to move
   */
  function buddypress_copy( $args, $assoc_args ) {
    if (!empty($assoc_args['from'] && !empty($assoc_args['to']))) {
      $this->force_replace_xprofile_fields( $assoc_args );
    }
  }
  private function force_replace_xprofile_fields( $assoc_args ) {
    $individual_profile_field = array(
      'Bio',
      'Languages',
      'Location',
      'Preferred Country Chapter',
      'Areas of Interest',
      'Links'
    );
    foreach( $individual_profile_field as $field ) {
      $source_field = xprofile_get_field_data($field, intval( $assoc_args['from'] ) );
      xprofile_delete_field_data( $field, intval( $assoc_args['to'] ) );

      $target_field =  xprofile_set_field_data($field, intval( $assoc_args['to'] ) ,  $source_field );
      if ( $target_field ) {
        WP_CLI::log('Buddypress field updated: '. $field);
      }
    }
  }
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
      if ( $this->does_user_exist( $assoc_args['from'] ) && $this->does_user_exist( $assoc_args['to'] ) ) {
        WP_CLI::log('Moving data from user ID:  '. $assoc_args['from'] .' to '. $assoc_args['to']);
        $origin_user_type = ccgn_applicant_type_desc( intval( $assoc_args['from'] ) );
        $this->link_old_entries( $assoc_args );
        //set user type
        $this->set_user_type( $assoc_args, $origin_user_type );
        //set user_roles
        $this->set_user_roles( $assoc_args );
        $this->switch_nicenames( $assoc_args );
        ccgn_create_profile( intval( $assoc_args['to'] ) );
        $this->replace_xprofile_fields( $assoc_args, $origin_user_type );

        _ccgn_registration_user_set_stage( intval( $assoc_args['to'] ), CCGN_APPLICATION_STATE_ACCEPTED );
        
        WP_CLI::success( 'Profile moved' );
      } else {
        WP_CLI::error( 'The specified user ID does not exist' );
      }

    } else {
      WP_CLI::error( 'You need to set --from and --to parameters' );
    }
  }
  private function replace_xprofile_fields( $assoc_args, $origin_user_type ) {
    $individual_profile_field = array(
      'Bio',
      'Languages',
      'Location',
      'Preferred Country Chapter',
      'Areas of Interest',
      'Links'
    );
    $institutional_profile_field = array(
      'Website',
      'Representative'
    );
    $list_fields = ( $origin_user_type == 'Individual' ) ? $individual_profile_field : $institutional_profile_field;
    
    foreach( $list_fields as $field ) {
      $source_field = xprofile_get_field_data($field, intval( $assoc_args['from'] ) );
      $target_field =  xprofile_set_field_data($field, intval( $assoc_args['to'] ) ,  $source_field );
      if ( $target_field ) {
        WP_CLI::log('Buddypress field updated: '. $field);
      }
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
  private function set_user_type( $assoc_args, $origin_user_type ) {
    
    if ( $origin_user_type == 'Individual' ) {
      ccgn_user_set_individual_applicant( intval( $assoc_args['to'] ) );
      ccgn_user_level_set_member_individual( intval( $assoc_args['to'] ) );
    } else if ( $origin_user_type == 'Institution' ) {
      ccgn_user_set_institutional_applicant( intval( $assoc_args['to'] ) );
      ccgn_user_level_set_member_institution( intval( $assoc_args['to'] ) );
    }
  }
  private function does_user_exist( int $user_id ) : bool {
    return (bool) get_users( [ 'include' => $user_id, 'fields' => 'ID' ] );
  }
}

function ccgn_cli_register_commands() {
  WP_CLI::add_command( 'ccgn', 'CCGN_cli' );
}
add_action( 'cli_init', 'ccgn_cli_register_commands' );