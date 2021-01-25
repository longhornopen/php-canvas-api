<?php

namespace LonghornOpen\CanvasApi;

use PHPUnit\Framework\TestCase;

class BaseHttpOperationsTest extends TestCase
{
    protected $api;

    protected function setUp(): void
    {
        // These tests assume a non-admin user, who is enrolled in multiple courses
        // FIXME: Figure out how best to read host and key from a config file or something, since they'll be different for everybody
        $api_host = 'http://canvas.docker';
        $access_key = 'abc123';
        $this->api = new CanvasApiClient($api_host, $access_key);
    }

    public function testNonPaginatedItemGet()
    {
        // Can we get a single item from the Canvas API?
        $me = $this->api->get('/users/self');
        $this->assertIsObject($me);
        $this->assertObjectHasAttribute('name', $me);
    }

    public function testPaginatedListGetZeroItems()
    {
        $accounts = $this->api->get('/accounts/');
        $this->assertIsArray($accounts);
        $this->assertSame(0, count($accounts));
    }

    public function testUnauthorized()
    {
        $this->expectException(CanvasApiException::class);
        $this->expectExceptionCode(401);

        $single_account_id = '1';
        $this->api->get('/accounts/' . $single_account_id);
    }

    public function testNonPaginatedListGet()
    {
        $courses = $this->api->get('/courses');
        $this->assertIsArray($courses);
        $this->assertGreaterThan(1, count($courses));
    }

    public function testPaginatedListGet()
    {
        $courses = $this->api->get('/courses?per_page=1');
        $this->assertIsArray($courses);
        $this->assertGreaterThan(1, count($courses));
    }

    public function testArrayGet()
    {
        $students = $this->api->get('/courses/2/students');
        $this->assertIsArray($students);
    }

    public function testGetSquareBracketParams()
    {
        $courses = $this->api->get('/courses?include[]=term&include[]=account');
        $this->assertIsArray($courses);
        $this->assertGreaterThan(1, count($courses));
        $course = $courses[0];
        $this->assertObjectHasAttribute('term', $course);
        $this->assertObjectHasAttribute('account', $course);
    }

    public function testPostSquareBracketParams()
    {
        $convos = $this->api->post('/conversations', [
            'recipients' => ['2', '3'],
            'subject' => 'Test Message',
            'body' => 'Test Message here',
            'force_new' => true
        ]);
        $this->assertIsArray($convos);
        $this->assertEquals(1, count($convos));
    }

    public function testPostPutDeleteLifecycle()
    {
        $courses = $this->api->get('/courses');
        $course = $courses[0];
        $assignment_name = "unit_" . time();

        // Should not have an assignment
        $assignments = $this->api->get('/courses/' . $course->id . '/assignments');
        $matching_assignments = array_filter($assignments, function ($assn) use ($assignment_name) {
            return $assn->name === $assignment_name;
        });
        $this->assertCount(0, $matching_assignments);

        // Create an assignment
        $assignment_description = "unit test 1";
        $assn = $this->api->post('/courses/' . $course->id . '/assignments', [
            'assignment' => [
                'name' => $assignment_name,
                'description' => $assignment_description
            ]
        ]);
        $this->assertEquals($assignment_name, $assn->name);
        $this->assertEquals($assignment_description, $assn->description);

        // Should have an assignment
        $assignments = $this->api->get('/courses/' . $course->id . '/assignments');
        $matching_assignments = array_values(array_filter($assignments, function ($assn) use ($assignment_name) {
            return $assn->name === $assignment_name;
        }));
        $this->assertCount(1, $matching_assignments);
        $this->assertEquals($assignment_description, $matching_assignments[0]->description);

        // Edit the assignment
        $assignment_description = "unit test 2";
        $assn = $this->api->put('/courses/' . $course->id . '/assignments/' . $matching_assignments[0]->id, [
            'assignment' => [
                'description' => $assignment_description
            ]
        ]);
        $this->assertEquals($assignment_name, $assn->name);
        $this->assertEquals($assignment_description, $assn->description);

        // Assignment should be edited
        $assignments = $this->api->get('/courses/' . $course->id . '/assignments');
        $matching_assignments = array_values(array_filter($assignments, function ($assn) use ($assignment_name) {
            return $assn->name === $assignment_name;
        }));
        $this->assertCount(1, $matching_assignments);
        $this->assertEquals($assignment_description, $matching_assignments[0]->description);

        // Delete the assignment
        $this->api->delete('/courses/' . $course->id . '/assignments/' . $matching_assignments[0]->id);

        // Assignment should be gone
        $assignments = $this->api->get('/courses/' . $course->id . '/assignments');
        $matching_assignments = array_filter($assignments, function ($assn) use ($assignment_name) {
            return $assn->name === $assignment_name;
        });
        $this->assertCount(0, $matching_assignments);
    }

    public function testCleanDataForJSON() {
        $data = $this->api->cleanDataForJSON(['foo' => 'bar']);
        $this->assertEquals('bar', $data['foo']);

        $data = $this->api->cleanDataForJSON(['foo' => [1,2]]);
        $this->assertEquals([1,2], $data['foo']);

        $data = $this->api->cleanDataForJSON(['foo[]' => [1,2]]);
        $this->assertEquals([1,2], $data['foo']);

        $data = $this->api->cleanDataForJSON(['assignment[name]' => 'myname']);
        $this->assertEquals('myname', $data['assignment']['name']);
    }
}