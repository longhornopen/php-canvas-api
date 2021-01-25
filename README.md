# php-canvas-api
PHP library for talking to Instructure's Canvas via its API.

## Composer installation

_WRITEME_

## Usage examples

        use Laits\CanvasApi\CanvasApi;
        
        $api_host = 'myschool.instructure.com';
        $access_key = '1234567890abcdef....';
        $api = new CanvasApi($api_host, $access_key);
        
        // Some API calls return a single object, which will be given as a stdClass.
        $me = $api->get('/users/self');
        echo($me->name);
        
        // Some API calls return lists, which will be given as PHP7 Iterators.
        $my_courses = $api->get('/courses?per_page=100');
        foreach ($my_courses as $my_course) {
            echo($my_course->name);
        }

## Version history
* 1.0 - public release
