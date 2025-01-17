<?php
/**
 * COmanage Registry CO Service Model
 *
 * Portions licensed to the University Corporation for Advanced Internet
 * Development, Inc. ("UCAID") under one or more contributor license agreements.
 * See the NOTICE file distributed with this work for additional information
 * regarding copyright ownership.
 *
 * UCAID licenses this file to you under the Apache License, Version 2.0
 * (the "License"); you may not use this file except in compliance with the
 * License. You may obtain a copy of the License at:
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @link          http://www.internet2.edu/comanage COmanage Project
 * @package       registry
 * @since         COmanage Registry v2.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
  
class CoService extends AppModel {
  // Define class name for cake
  public $name = "CoService";
  
  // Current schema version for API
  public $version = "1.0";
  
  // Add behaviors
  public $actsAs = array('Containable',
                         'Provisioner',
                         'Changelog' => array('priority' => 5));
  
  // Association rules from this model to other models
  public $belongsTo = array(
    "Authenticator",
    "Co",
    "CoGroup",
    "Cou"
  );
  
  // Default display field for cake generated views
  public $displayField = "name";
  
  // Validation rules for table elements
  public $validate = array(
    'co_id' => array(
      'rule' => 'numeric',
      'required' => true,
      'allowEmpty' => false
    ),
    'cou_id' => array(
      'content' => array(
        'rule' => 'numeric',
        'required' => false,
        'allowEmpty' => true,
        'unfreeze' => 'CO'
      )
    ),
    'name' => array(
      'rule' => array('validateInput'),
      'required' => true,
      'allowEmpty' => false
    ),
    'description' => array(
      'rule' => array('validateInput'),
      'required' => false,
      'allowEmpty' => true
    ),
    'short_label' => array(
      'rule' => array('alphaNumeric'),
      'required' => false,
      'allowEmpty' => true
      // XXX this should put up an alphanumeric error message rather than "this field cannot be left blank"
    ),
    'co_group_id' => array(
      'content' => array(
        'rule' => 'numeric',
        'required' => false,
        'allowEmpty' => true,
        'unfreeze' => 'CO'
      )
    ),
    'cluster_id' => array(
      'content' => array(
        'rule' => 'numeric',
        'required' => false,
        'allowEmpty' => true,
        'unfreeze' => 'CO'
      )
    ),
    'service_url' => array(
      // We can't set this to 'url' because url validation doesn't understand ssh:
      'rule' => array('validateInput'),
      'required' => false,
      'allowEmpty' => true
    ),
    // Deprecated (CO-1595)
    'service_label' => array(
      'rule' => array('validateInput'),
      'required' => false,
      'allowEmpty' => true
    ),
    'contact_email' => array(
      'rule' => array('email'),
      'required' => false,
      'allowEmpty' => true
    ),
    'logo_url' => array(
      'rule' => array('url', true),
      'required' => false,
      'allowEmpty' => true
    ),
    'entitlement_uri' => array(
      'rule' => 'notBlank',
      'required' => false,
      'allowEmpty' => true
    ),
    'visibility' => array(
      'rule' => array('inList', array(VisibilityEnum::CoAdmin,
                                      VisibilityEnum::CoGroupMember,
                                      VisibilityEnum::CoMember,
                                      VisibilityEnum::Unauthenticated)),
      'required' => true,
      'allowEmpty' => false
    ),
    'identifier_type' => array(
      'rule' => array('validateInput'),
      'required' => false,
      'allowEmpty' => true
    ),
    'authenticator_id' => array(
      'content' => array(
        'rule' => 'numeric',
        'required' => false,
        'allowEmpty' => true,
        'unfreeze' => 'CO'
      )
    ),
    'status' => array(
      'rule' => array('inList', array(SuspendableStatusEnum::Active,
                                      SuspendableStatusEnum::Suspended)),
      'required' => true,
      'allowEmpty' => false
    )
  );
  
  // Enum type hints
  
  public $cm_enum_types = array(
    'status' => 'SuspendableStatusEnum',
    'visibility' => 'VisibilityEnum'
  );
  
  /**
   * Actions to take after a save operation is executed.
   *
   * @since  COmanage Registry v3.2.0
   * @param  boolean $created True if a new record was created (rather than update)
   * @param  array   $options As passed into Model::save()
   */

  public function afterSave($created, $options = array()) {
    // Clear any transaction. (_commit() will see if one is active.)
    $this->_commit();
    
    return true;
  }

  /**
   * Actions to take before a save operation is executed.
   *
   * @since  COmanage Registry v3.2.0
   * @param  array   $options As passed into Model::save()
   * @return Boolean True to continue with save, false to abort
   */

  public function beforeSave($options = array()) {
    // Start a transaction -- we'll commit in afterSave
    $this->_begin();
    
    // Check that short_label and cluster_id are not already in use.
    // XXX This should be replaced with a validator (CO-1559).
    
    foreach(array('cluster_id', 'short_label') as $field) {
      if(!empty($this->data['CoService'][$field])) {
        $args = array();
        if($field == 'cluster_id') {
          $args['conditions']['CoService.'.$field] = $this->data['CoService'][$field];
        } else {
          // XXX Does this need a special database index (for LOWER())?
          $args['conditions']['LOWER(CoService.'.$field.')'] = strtolower($this->data['CoService'][$field]);
        }
        $args['conditions']['CoService.co_id'] = $this->data['CoService']['co_id'];
        if(!empty($this->data['CoService']['id'])) {
          // Skip our own ID if we're updating
          $args['conditions']['NOT']['CoService.id'] = $this->data['CoService']['id'];
        }
        
        $svcs = $this->findForUpdate($args['conditions'], array('id'));
        
        if(!empty($svcs)) {
          $this->_rollback();
          
          throw new InvalidArgumentException(_txt('er.svc.'.$field.'.exists', array(filter_var($this->data['CoService'][$field], FILTER_SANITIZE_SPECIAL_CHARS))));
        }
      }
    }
    
    return true;
  }
  
  /**
   * Find CO Services visible to the specified CO Person.
   *
   * @since  COmanage Registry v2.0.0
   * @param  RoleComponent
   * @param  Integer $coId       CO ID
   * @param  Integer $coPersonId CO Person ID, or null for public services
   * @param  Integer @couId      COU ID, null for CO level services, or false for all services within the CO
   * @return Array Array of CO Services
   */
  
  public function findServicesByPerson($Role, $coId, $coPersonId=null, $couId=null) {
    // First determine which visibilities to retrieve. Unlike most other cases,
    // we do NOT treat admins specially. They can look in the configuration
    // if they need to see the complete list.
    
    $visibility = array(VisibilityEnum::Unauthenticated);
    $groups = null;
    
    if($coPersonId) {
      // Is this person an admin?
      
      if($Role->isCoAdmin($coPersonId, $coId)) {
        $visibility[] = VisibilityEnum::CoAdmin;
      }
      
      if($Role->isCoPerson($coPersonId, $coId)) {
        $visibility[] = VisibilityEnum::CoMember;
        
        // The join on CoGroupMember would be way too complicated, it'd be easier
        // to just pull two queries and merge. Instead, we'll just pull everything
        // flagged for CoGroupMember and then filter the results manually based on
        // the person's groups.
        $visibility[] = VisibilityEnum::CoGroupMember;
        
        $groups = $this->Co->CoGroup->findForCoPerson($coPersonId, null, null, null, false);
      }
    }
    
    $args = array();
    $args['conditions']['CoService.co_id'] = $coId;
    if($couId !== false) {
      // COU ID does not constrain visibility, it's basically like having a COU level portal
      $args['conditions']['CoService.cou_id'] = $couId;
    }
    $args['conditions']['CoService.visibility'] = $visibility;
    $args['conditions']['CoService.status'] = SuspendableStatusEnum::Active;
    $args['order'] = 'CoService.name';
    $args['contain'] = false;
    
    $services = $this->find('all', $args);
    $groupIds = array();
    
    if(!empty($groups) && !empty($services) && $coPersonId) {
      // If $coPersonId is not set, there won't be any services with a CoGroupMember visibility
      
      $groupIds = Hash::extract($groups, '{n}.CoGroup.id');
    }
    
    // Walk the list of services and remove any with a group_id that doesn't match
    
    for($i = count($services) - 1;$i >= 0;$i--) {
      if($services[$i]['CoService']['visibility'] == VisibilityEnum::CoGroupMember
         && $services[$i]['CoService']['co_group_id']
         && !in_array($services[$i]['CoService']['co_group_id'], $groupIds)) {
        unset($services[$i]);
      }
    }
    
    return $services;
  }
  
  /**
   * Map Authenticators to a Service and Authenticator plugin configuration,
   * suitable for use in Provisioners.
   *
   * @since  COmanage Registry v3.3.0
   * @param  int $coId CO ID
   * @return array     Array of Service and Authenticator configurations
   */
  
  public function mapAuthenticators($coId) {
    // Pull all CO Services in $coId where authenticator_id is not null
    // then map those to PLUGIN_authenticator_id
    // then return (label, plugin, PLUGIN_authenticator_id)
    
    $ret = array();
    
    $args = array();
    $args['conditions']['CoService.co_id'] = $coId;
    $args['conditions']['CoService.status'] = SuspendableStatusEnum::Active;
    $args['conditions'][] = 'CoService.authenticator_id IS NOT NULL';
    $args['contain'] = array('Authenticator');
    
    $services = $this->find('all', $args);
    
    if(!empty($services)) {
      // Instantiate the plugin and pull its configuration
      
      foreach($services as $svc) {
        if(!empty($svc['Authenticator']['plugin'])) {
          // This could really be a call in Model/Authenticator() ...
          $pluginName = $svc['Authenticator']['plugin'];
          $pluginModel = $svc['Authenticator']['plugin'] . '.' . $svc['Authenticator']['plugin'];
          
          $Plugin = ClassRegistry::init($pluginModel);
          
          $args = array();
          $args['conditions'][$pluginName.'.authenticator_id'] = $svc['Authenticator']['id'];
          $args['contain'] = false;
          
          $pluginCfg = $Plugin->find('first', $args);
          
          if(!empty($pluginCfg[$pluginName])) {
            $ret[ $svc['CoService']['id'] ] = array(
              'serviceCfg' => $svc['CoService'],
              'pluginType' => $pluginName,
              'cfg'        => $pluginCfg
            );
          }
        }
      }
    }
    
    return $ret;
  }
  
  /**
   * Map a Cluster to a CO Service short label.
   *
   * @since  COmanage Registry v3.3.0
   * @param  Integer $coId      CO ID
   * @param  Integer $clusterId Cluster ID
   * @return String             The short label, if found
   */
  
  public function mapClusterToLabel($coId, $clusterId) {
    // There should only be one instance of $clusterId across all Services
    // within a CO, since we have to use that label when provisioning and we
    // won't know how to handle multiple labels.
    
    $args = array();
    $args['CoService.co_id'] = $coId; // Strictly speaking we don't need this
    $args['CoService.cluster_id'] = $clusterId;
    $args['CoService.status'] = SuspendableStatusEnum::Active;
    
    return $this->field('short_label', $args);
  }
  
  /**
   * Map a list of groups to the entitlements they are associated with.
   *
   * @since  COmanage Registry v2.0.0
   * @param  Integer CO ID
   * @param  Array Array of CO Group IDs
   * @return Array Array of entitlements, keyed on CO Service ID
   */
  
  public function mapCoGroupsToEntitlements($coId, $coGroupIds) {
    $args = array();
    $args['conditions']['CoService.co_id'] = $coId;
    $args['conditions']['OR']['CoService.co_group_id'] = $coGroupIds;
    $args['conditions']['OR'][] = 'CoService.co_group_id IS NULL';
    $args['conditions']['CoService.status'] = SuspendableStatusEnum::Active;
    $args['conditions'][] = 'CoService.entitlement_uri IS NOT NULL';
    $args['conditions']['NOT']['CoService.entitlement_uri'] = '';
    $args['fields'] = array('CoService.id', 'CoService.entitlement_uri');
    $args['contain'] = false;
    
    return $this->find('list', $args);
  }
  
  /**
   * Map an identifier type to the label(s) of any associated CoService.
   * Note multiple Services can use the same identifier type, so this returns an array.
   *
   * @since  COmanage Registry v3.2.0
   * @param  Integer $coId           CO ID
   * @param  String  $identifierType Identifier type
   * @param  String  $label          Label type
   * @return Array Array of labels, keyed on CO Service ID
   * @todo Currently only "short_label" supported
   */
  
  public function mapIdentifierToLabels($coId, $identifierType, $label="short_label") {
    $args = array();
    $args['conditions']['CoService.co_id'] = $coId;
    $args['conditions']['CoService.identifier_type'] = $identifierType;
    $args['fields'] = array('CoService.id', 'CoService.'.$label);
    $args['contain'] = false;
    
    return $this->find('list', $args);
  }
 
  /**
   * Perform a keyword search.
   *
   * @since  COmanage Registry v3.1.0
   * @param  integer $coId  CO ID to constrain search to
   * @param  string  $q     String to search for
   * @param  integer $limit Search limit
   * @return Array Array of search results, as from find('all)
   */
  
  public function search($coId, $q, $limit) {
    // Tokenize $q on spaces
    $tokens = explode(" ", $q);
    
    $args = array();
    foreach($tokens as $t) {
      $args['conditions']['AND'][] = array(
        'OR' => array(
          'LOWER(CoService.name) LIKE' => '%' . strtolower($t) . '%'
        )
      );
    }
    $args['conditions']['CoService.co_id'] = $coId;
    $args['order'] = array('CoService.name');
    $args['limit'] = $limit;
    $args['contain'] = false;
    
    return $this->find('all', $args);
  }
  
  /**
   * Set a membership attribute for the group associated with the CO Service.
   *
   * @param  Integer $id              CO Service ID
   * @param  RoleComponent $Role      RoleComponent
   * @param  Integer $coPersonId      CO Person ID to set membership for
   * @param  Integer $actorCoPersonId CO Person ID of requestor
   * @param  String  $action          Action: "join" or "leave"
   * @return Boolean True on success
   * @throws InvalidArgumentException
   * @throws RuntimeException
   */
  
  public function setServiceGroupMembership($id, $Role, $coPersonId, $actorCoPersonId, $action="join") {
    // First see if there is even a group associated with this service
    
    $coGroupId = $this->field('co_group_id', array('CoService.id' => $id));
    
    if(!$coGroupId) {
      throw new InvalidArgumentException(_txt('er.svc.group.none'));
    }
    
    // setMembership will take care of logical checks (open group, etc).
    // Let any exception pass up the stack.
    
    $member = ($action == "join");
    $owner = false;
    
    $this->CoGroup
         ->CoGroupMember->setMembership($Role,
                                        $coGroupId,
                                        $coPersonId,
                                        $member,
                                        $owner,
                                        $actorCoPersonId);
         
    return true;
  }
}
