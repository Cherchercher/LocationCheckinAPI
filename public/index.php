<?php
require_once dirname(__FILE__) . '/../bootstrap.php';

use API\Middleware\TokenOverBasicAuth;
use API\Exception;
use API\Exception\ValidationException;

// General API group
$app->group(
    '/api',
    function () use ($app, $log) {

        // Common to all sub routes

        // Get users
        $app->get('/', function () {
            echo "<h1>This can be the documentation entry point</h1>";
            echo "<p>This URL could also contain discovery"
            ." information in side the headers</p>";
        });

        // Group for API Version 1
        $app->group(
            '/v1',
            // API Methods
            function () use ($app, $log) {

                // Get users
                $app->get(
                    '/users',
                    function () use ($app, $log) {

                        $users = array();
                        $filters = array();
                        $total = 0;
                        $pages = 1;

                        // Default resultset
                        $results = \ORM::forTable('users');

                        // Get and sanitize filters from the URL
                        if ($rawfilters = $app->request->get()) {
                            unset(
                                $rawfilters['sort'],
                                $rawfilters['fields'],
                                $rawfilters['page'],
                                $rawfilters['per_page']
                            );
                            foreach ($rawfilters as $key => $value) {
                                $filters[$key] = filter_var(
                                    $value,
                                    FILTER_SANITIZE_STRING
                                );
                            }

                        }

                        // Add filters to the query
                        if (!empty($filters)) {
                            foreach ($filters as $key => $value) {
                                if ('q' == $key) {
                                    $results->whereRaw(
                                        '(`firstname` LIKE ? OR `email` LIKE ?)',
                                        array('%'.$value.'%', '%'.$value.'%')
                                    );
                                } else {
                                    $results->where($key, $value);
                                }
                            }

                        }

                        // Get and sanitize field list from the URL
                        if ($fields = $app->request->get('fields')) {
                            $fields = explode(',', $fields);
                            $fields = array_map(
                                function ($field) {
                                    $field = filter_var(
                                        $field,
                                        FILTER_SANITIZE_STRING
                                    );
                                    return trim($field);
                                },
                                $fields
                            );
                        }

                        // Add field list to the query
                        if (is_array($fields) && !empty($fields)) {
                            $results->selectMany($fields);
                        }


                        // Manage sort options
                        // sort=firstname => ORDER BY firstname ASC
                        // sort=-firstname => ORDER BY firstname DESC
                        // sort=-firstname,email =>
                        // ORDER BY firstname DESC, email ASC
                        if ($sort = $app->request->get('sort')) {
                            $sort = explode(',', $sort);
                            $sort = array_map(
                                function ($s) {
                                    $s = filter_var($s, FILTER_SANITIZE_STRING);
                                    return trim($s);
                                },
                                $sort
                            );
                            foreach ($sort as $expr) {
                                if ('-' == substr($expr, 0, 1)) {
                                    $results->orderByDesc(substr($expr, 1));
                                } else {
                                    $results->orderByAsc($expr);
                                }
                            }
                        }


                        // Manage pagination
                        $page = filter_var(
                            $app->request->get('page'),
                            FILTER_SANITIZE_NUMBER_INT
                        );
                        if (!empty($page)) {

                            $perPage = filter_var(
                                $app->request->get('per_page'),
                                FILTER_SANITIZE_NUMBER_INT
                            );
                            if (empty($perPage)) {
                                $perPage = 10;
                            }

                            // Total after filters and
                            // before pagination limit
                            $total = $results->count();

                            // Compute the pagination Link header
                            $pages = ceil($total / $perPage);

                            // Base for all links
                            $linkBaseURL = $app->request->getUrl()
                                . $app->request->getRootUri()
                                . $app->request->getResourceUri();

                            // Init empty vars
                            $queryString = array();
                            $links = array();
                            $next =  '';
                            $last = '';
                            $prev =  '';
                            $first = '';

                            // Adding fields
                            if (!empty($fields)) {
                                $queryString[] = 'fields='
                                    . join(
                                        ',',
                                        array_map(
                                            function ($f) {
                                                return urlencode($f);
                                            },
                                            $fields
                                        )
                                    );
                            }

                            // Adding filters
                            if (!empty($filters)) {
                                $queryString[] = http_build_query($filters);
                            }

                            // Adding sort options
                            if (!empty($sort)) {
                                $queryString[] = 'sort='
                                    . join(
                                        ',',
                                        array_map(
                                            function ($s) {
                                                return urlencode($s);
                                            },
                                            $sort
                                        )
                                    );
                            }

                            // Next and Last link
                            if ($page < $pages) {
                                $next = $linkBaseURL . '?' . join(
                                    '&',
                                    array_merge(
                                        $queryString,
                                        array(
                                            'page=' . (string) ($page + 1),
                                            'per_page=' . $perPage
                                        )
                                    )
                                );
                                $last = $linkBaseURL . '?' . join(
                                    '&',
                                    array_merge(
                                        $queryString,
                                        array(
                                            'page=' . (string) $pages,
                                            'per_page=' . $perPage
                                        )
                                    )
                                );

                                $links[] = sprintf('<%s>; rel="next"', $next);
                                $links[] = sprintf('<%s>; rel="last"', $last);
                            }

                            // Previous and First link
                            if ($page > 1 && $page <= $pages) {
                                $prev = $linkBaseURL . '?' . join(
                                    '&',
                                    array_merge(
                                        $queryString,
                                        array(
                                            'page=' . (string) ($page - 1),
                                            'per_page=' . $perPage
                                        )
                                    )
                                );
                                $first = $linkBaseURL . '?' . join(
                                    '&',
                                    array_merge(
                                        $queryString,
                                        array(
                                            'page=1', 'per_page=' . $perPage
                                        )
                                    )
                                );
                                $links[] = sprintf('<%s>; rel="prev"', $prev);
                                $links[] = sprintf('<%s>; rel="first"', $first);
                            }

                            // Set header if required
                            if (!empty($links)) {
                                $app->response->headers->set(
                                    'Link',
                                    join(',', $links)
                                );
                            }

                            $results->limit($perPage)
                                ->offset($page * $perPage - $perPage);
                        }


                        $users = $results->findArray();

                        if (empty($total)) {
                            $total = count($users);
                        }
                        $app->response->headers->set('X-Total-Count', $total);

                        echo json_encode($users, JSON_PRETTY_PRINT);
                    }
                );

                // Get user with ID
                $app->get(
                    '/users/:id',
                    function ($id) use ($app, $log) {

                        $id = filter_var(
                            filter_var($id, FILTER_SANITIZE_NUMBER_INT),
                            FILTER_VALIDATE_INT
                        );

                        if (false === $id) {
                            throw new ValidationException("Invalid user ID");
                        }

                        $user = \ORM::forTable('users')->findOne($id);
                        if ($user) {

                            $output = $user->asArray();

                            if ('places' === $app->request->get('embed')) {
                                $places = \ORM::forTable('places')
                                    ->where('user_id', $id)
                                    ->orderByDesc('id')
                                    ->findArray();

                                if (!empty($places)) {
                                    $output['places'] = $places;
                                }
                            }

                            echo json_encode($output, JSON_PRETTY_PRINT);
                            return;
                        }
                        $app->notFound();
                    }
                );

                // Adds new user
                $app->post(
                    '/users',
                    function () use ($app, $log) {

                        $body = $app->request()->getBody();

                        $errors = $app->validateuser($body);

                        if (empty($errors)) {
                            $user = \ORM::for_table('users')->create();

                            if (isset($body['places'])) {
                                $places = $body['places'];
                                unset($body['places']);
                            }

                            $user->set($body);

                            if (true === $user->save()) {

                                // Insert places
                                if (!empty($places)) {
                                    $userPlaces = array();
                                    foreach ($users as $item) {
                                        $item['user_id'] = $user->id;
                                        $place = \ORM::for_table('places')
                                            ->create();
                                        $place->set($item);
                                        if (true === $place->save()) {
                                            $userplaces[] = $place->asArray();
                                        }
                                    }
                                }

                                $output = $user->asArray();
                                if (!empty($userplaces)) {
                                    $output['places'] = $userplaces;
                                }
                                echo json_encode($output, JSON_PRETTY_PRINT);
                            } else {
                                throw new Exception("Unable to save user");
                            }

                        } else {
                            throw new ValidationException(
                                "Invalid data",
                                0,
                                $errors
                            );
                        }
                    }
                );

                // Update user with ID
                $app->map(
                    '/users/:id',
                    function ($id) use ($app, $log) {

                        $user = \ORM::forTable('users')->findOne($id);

                        if ($user) {

                            $body = $app->request()->getBody();

                            $errors = $app->validateuser($body, 'update');

                            if (empty($errors)) {

                                if (isset($body['places'])) {
                                    $places = $body['places'];
                                    unset($body['places']);
                                }

                                $user->set($body);

                                if (true === $user->save()) {

                                    // Process places
                                    if (!empty($places)) {
                                        $userplaces = array();
                                        foreach ($places as $item) {

                                            $item['user_id'] = $user->id;

                                            if (empty($item['id'])) {

                                                // New place
                                                $place = \ORM::for_table('places')
                                                    ->create();
                                            } else {

                                                // Existing place
                                                $place = \ORM::forTable('places')
                                                    ->findOne($item['id']);
                                            }

                                            if ($place) {
                                                $place->set($item);
                                                if (true === $place->save()) {
                                                    $userplaces[] = $place->asArray();
                                                }
                                            }
                                        }
                                    }

                                    $output = $user->asArray();
                                    if (!empty($userplaces)) {
                                        $output['places'] = $userplaces;
                                    }
                                    echo json_encode(
                                        $output,
                                        JSON_PRETTY_PRINT
                                    );
                                    return;

                                } else {
                                    throw new Exception(
                                        "Unable to save user"
                                    );
                                }

                            } else {
                                throw new ValidationException(
                                    "Invalid data",
                                    0,
                                    $errors
                                );
                            }

                        }

                        $app->notFound();
                    }
                )->via('PUT', 'PATCH');


                // Delete user with ID
                $app->delete(
                    '/users/:id',
                    function ($id) use ($app, $log) {

                        $user = \ORM::forTable('users')->findOne($id);

                        if ($user) {

                            $user->delete();

                            $app->halt(204);
                        }

                        $app->notFound();
                    }
                );


                // Add user to favorites
                $app->put(
                    '/users/:id/star',
                    function ($id) use ($app, $log) {
                        
                        $user = \ORM::forTable('users')->findOne($id);

                        if ($user) {
                            $user->set('favorite', 1);
                            if (true === $user->save()) {
                                $output = $user->asArray();
                                echo json_encode(
                                    $output,
                                    JSON_PRETTY_PRINT
                                );
                                return;
                            } else {
                                throw new Exception(
                                    "Unable to save user"
                                );
                            }
                        }

                        $app->notFound();
                    }
                );

                // Remove user from favorites
                $app->delete(
                    '/users/:id/star',
                    function ($id) use ($app, $log) {
                        $user = \ORM::forTable('users')->findOne($id);

                        if ($user) {
                            $user->set('favorite', 0);
                            if (true === $user->save()) {
                                $output = $user->asArray();
                                echo json_encode(
                                    $output,
                                    JSON_PRETTY_PRINT
                                );
                                return;
                            } else {
                                throw new Exception(
                                    "Unable to save user"
                                );
                            }
                        }
                        $app->notFound();
                    }
                );

                // Get places for user
                $app->get(
                    '/users/:id/places',
                    function ($id) use ($app, $log) {
                        
                        $user = \ORM::forTable('users')
                            ->select('id')->findOne($id);

                        if ($user) {
                            $places = \ORM::forTable('places')
                                ->where('user_id', $id)->findArray();
                            echo json_encode($places, JSON_PRETTY_PRINT);
                            return;
                        }

                        $app->notFound();
                    }
                );

                // Add a new place for user with id :id
                $app->post(
                    '/users/:id/places',
                    function ($id) use ($app, $log) {

                        $user = \ORM::forTable('users')
                            ->select('id')->findOne($id);

                        if ($user) {

                            $body = $app->request()->getBody();

                            $errors = $app->validateplace($body);
                            
                            if (empty($errors)) {
                                
                                $place = \ORM::for_table('places')
                                    ->create();

                                $place->set($body);
                                $place->user_id = $id;
                                
                                if (true === $place->save()) {
                                    
                                    echo json_encode(
                                        $place->asArray(),
                                        JSON_PRETTY_PRINT
                                    );
                                    return;
                                } else {
                                    throw new Exception(
                                        "Unable to save place"
                                    );
                                }
                                
                            } else {
                                throw new ValidationException(
                                    "Invalid data",
                                    0,
                                    $errors
                                );
                            }

                        }
                        $app->notFound();
                    }
                );

                // Get single place
                $app->get(
                    '/users/:id/places/:place_id',
                    function ($id, $place_id) use ($app, $log) {

                        $id = filter_var(
                            filter_var($id, FILTER_SANITIZE_NUMBER_INT),
                            FILTER_VALIDATE_INT
                        );

                        if (false === $id) {
                            throw new ValidationException("Invalid user ID");
                        }

                        $place_id = filter_var(
                            filter_var($place_id, FILTER_SANITIZE_NUMBER_INT),
                            FILTER_VALIDATE_INT
                        );

                        if (false === $place_id) {
                            throw new ValidationException("Invalid place ID");
                        }

                        $user = \ORM::forTable('users')
                            ->select('id')->findOne($id);

                        if ($user) {

                            $place = \ORM::forTable('places')->findOne($place_id);
                            
                            if ($place) {

                                echo json_encode(
                                    $place->asArray(),
                                    JSON_PRETTY_PRINT
                                );
                                return;
                            }

                        }
                        $app->notFound();
                    }
                );

                // Update a single place
                $app->map(
                    '/users/:id/places/:place_id',
                    function ($id, $place_id) use ($app, $log) {

                        $id = filter_var(
                            filter_var($id, FILTER_SANITIZE_NUMBER_INT),
                            FILTER_VALIDATE_INT
                        );

                        if (false === $id) {
                            throw new ValidationException("Invalid user ID");
                        }

                        $place_id = filter_var(
                            filter_var($place_id, FILTER_SANITIZE_NUMBER_INT),
                            FILTER_VALIDATE_INT
                        );

                        if (false === $place_id) {
                            throw new ValidationException("Invalid place ID");
                        }

                        $user = \ORM::forTable('users')
                            ->select('id')->findOne($id);
                        
                        if ($user) {
                            
                            $place = \ORM::forTable('places')->findOne($place_id);
                            
                            if ($place) {

                                $body = $app->request()->getBody();

                                $errors = $app->validateplace($body, 'update');
                                
                                if (empty($errors)) {
                                    $place->set('body', $body['body']);
                                    if (true === $place->save()) {
                                        
                                        echo json_encode(
                                            $place->asArray(),
                                            JSON_PRETTY_PRINT
                                        );
                                        return;

                                    } else {
                                        
                                        throw new Exception(
                                            "Unable to save place"
                                        );
                                    }
                                } else {
                                    throw new ValidationException(
                                        "Invalid data",
                                        0,
                                        $errors
                                    );

                                }

                            }
                        }
                        $app->notFound();
                    }
                )->via('PUT', 'PATCH');

                // Delete single place
                $app->delete(
                    '/users/:id/places/:place_id',
                    function ($id, $place_id) use ($app, $log) {

                        $user = \ORM::forTable('users')
                            ->select('id')->findOne($id);

                        if ($user) {

                            $place = \ORM::forTable('places')->findOne($place_id);
                            
                            if ($place) {
                                $place->delete();
                                $app->halt(204);
                            }

                        }

                        $app->notFound();
                    }
                );

            }
        );
    }
);

// Public human readable home page
$app->get(
    '/',
    function () use ($app, $log) {
        echo "<h1>Hello, this can be the public App Interface</h1>";
    }
);

// JSON friendly errors
// place: debug must be false
// or default error template will be printed
$app->error(function (\Exception $e) use ($app, $log) {

    $mediaType = $app->request->getMediaType();

    $isAPI = (bool) preg_match('|^/api/v.*$|', $app->request->getPath());

    // Standard exception data
    $error = array(
        'code' => $e->getCode(),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    );

    // Graceful error data for production mode
    if (!in_array(
        get_class($e),
        array('API\\Exception', 'API\\Exception\ValidationException')
    )
        && 'production' === $app->config('mode')) {
        $error['message'] = 'There was an internal error';
        unset($error['file'], $error['line']);
    }

    // Custom error data (e.g. Validations)
    if (method_exists($e, 'getData')) {
        $errors = $e->getData();
    }

    if (!empty($errors)) {
        $error['errors'] = $errors;
    }

    $log->error($e->getMessage());
    if ('application/json' === $mediaType || true === $isAPI) {
        $app->response->headers->set(
            'Content-Type',
            'application/json'
        );
        echo json_encode($error, JSON_PRETTY_PRINT);
    } else {
        echo '<html>
        <head><title>Error</title></head>
        <body><h1>Error: ' . $error['code'] . '</h1><p>'
        . $error['message']
        .'</p></body></html>';
    }

});

/// Custom 404 error
$app->notFound(function () use ($app) {

    $mediaType = $app->request->getMediaType();

    $isAPI = (bool) preg_match('|^/api/v.*$|', $app->request->getPath());


    if ('application/json' === $mediaType || true === $isAPI) {

        $app->response->headers->set(
            'Content-Type',
            'application/json'
        );

        echo json_encode(
            array(
                'code' => 404,
                'message' => 'Not found'
            ),
            JSON_PRETTY_PRINT
        );

    } else {
        echo '<html>
        <head><title>404 Page Not Found</title></head>
        <body><h1>404 Page Not Found</h1><p>The page you are
        looking for could not be found.</p></body></html>';
    }
});

$app->run();
