<?php
namespace API;

use Slim\Slim;

class Application extends Slim
{
    public function validateuser($user = array(), $action = 'create')
    {
        $errors = array();
        
        if (!empty($user['places'])) {
            $places = $user['places'];
            unset($user['places']);
        }

        $user = filter_var_array(
            $user,
            array(
                'id' => FILTER_SANITIZE_NUMBER_INT,
                'firstname' => FILTER_SANITIZE_STRING,
                'lastname' => FILTER_SANITIZE_STRING,
                'email' => FILTER_SANITIZE_EMAIL,
                'phone' => FILTER_SANITIZE_STRING,
            ),
            false
        );
        
        switch ($action) {
            
            case 'update':
                if (empty($user['id'])) {
                    $errors['user'][] = array(
                        'field' => 'id',
                        'message' => 'ID cannot be empty on update'
                    );
                    break;
                }
                if (isset($user['firstname'])
                    && empty($user['firstname'])) {
                    $errors['user'][] = array(
                        'field' => 'firstname',
                        'message' => 'First name cannot be empty'
                    );
                }
                if (isset($user['email'])) {
                    if (empty($user['email'])) {
                        $errors['user'][] = array(
                            'field' => 'email',
                            'message' => 'Email address cannot be empty'
                        );
                        break;
                    }
            
                    if (false === filter_var(
                        $user['email'],
                        FILTER_VALIDATE_EMAIL
                    )) {
                        $errors['user'][] = array(
                            'field' => 'email',
                            'message' => 'Email address is invalid'
                        );
                        break;
                    }
            
                    // Test for unique email
                    $results = \ORM::forTable('users')
                        ->where('email', $user['email'])->findOne();
                    if (false !== $results
                        && $results->id !== $user['id']) {
                        $errors['user'][] = array(
                            'field' => 'email',
                            'message' => 'Email address already exists'
                        );
                    }
                }
                break;
            
            case 'create':
            default:
                if (empty($user['firstname'])) {
                    $errors['user'][] = array(
                        'field' => 'firstname',
                        'message' => 'First name cannot be empty'
                    );
                }
                if (empty($user['email'])) {
                    $errors['user'][] = array(
                        'field' => 'email',
                        'message' => 'Email address cannot be empty'
                    );
                } elseif (false === filter_var(
                    $user['email'],
                    FILTER_VALIDATE_EMAIL
                )) {
                        $errors['user'][] = array(
                            'field' => 'email',
                            'message' => 'Email address is invalid'
                        );
                } else {
                
                    // Test for unique email
                    $results = \ORM::forTable('users')
                        ->where('email', $user['email'])->count();
                    if ($results > 0) {
                        $errors['user'][] = array(
                            'field' => 'email',
                            'message' => 'Email address already exists'
                        );
                    }
                }
                
                break;
        }
        

        if (!empty($places) && is_array($places)) {
            $placeCount = count($places);
            for ($i = 0; $i < $placeCount; $i++) {
                
                $placeErrors = $this->validateplace($places[$i], $action);
                if (!empty($placeErrors)) {
                    $errors['places'][] = $placeErrors;
                    unset($placeErrors);
                }

            }
        }

        return $errors;
    }
    
    public function validateplace($place = array(), $action = 'create')
    {
        $errors = array();

        $place = filter_var_array(
            $place,
            array(
                'id' => FILTER_SANITIZE_NUMBER_INT,
                'body' => FILTER_SANITIZE_STRING,
                'user_id' => FILTER_SANITIZE_NUMBER_INT,
            ),
            false
        );
        
        if (isset($place['body']) && empty($place['body'])) {
            $errors[] = array(
                'field' => 'body',
                'message' => 'place body cannot be empty'
            );
        }
        

        return $errors;
    }
}
