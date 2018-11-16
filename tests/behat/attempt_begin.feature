@mod @mod_ddtaquiz
Feature: The various checks that may happen when an attept is started
  As a student
  In order to start a ddtaquiz with confidence
  I need to be waned if there is a time limit, or various similar things

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | student  | Student   | One      | student@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student  | C1     | student |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype       | name  | questiontext               |
      | Test questions   | truefalse   | TF1   | Text of the first question |

  @javascript
  Scenario: Start a ddtaquiz with no time limit
    Given the following "activities" exist:
      | activity   | name   | intro              | course | idnumber |
      | ddtaquiz       | Ddtaquiz 1 | Ddtaquiz 1 description | C1     | ddtaquiz1    |
    And ddtaquiz "Ddtaquiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
    When I log in as "student"
    And I am on "Course 1" course homepage
    And I follow "Ddtaquiz 1"
    And I press "Attempt ddtaquiz now"
    Then I should see "Text of the first question"

  @javascript
  Scenario: Start a ddtaquiz with time limit and password
    Given the following "activities" exist:
      | activity   | name   | intro              | course | idnumber | timelimit | ddtaquizpassword |
      | ddtaquiz       | Ddtaquiz 1 | Ddtaquiz 1 description | C1     | ddtaquiz1    | 3600      | Frog         |
    And ddtaquiz "Ddtaquiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
    When I log in as "student"
    And I am on "Course 1" course homepage
    And I follow "Ddtaquiz 1"
    And I press "Attempt ddtaquiz now"
    Then I should see "To attempt this ddtaquiz you need to know the ddtaquiz password" in the "Start attempt" "dialogue"
    And I should see "The ddtaquiz has a time limit of 1 hour. Time will " in the "Start attempt" "dialogue"
    And I set the field "Ddtaquiz password" to "Frog"
    And I click on "Start attempt" "button" in the "Start attempt" "dialogue"
    And I should see "Text of the first question"

  @javascript
  Scenario: Cancel starting a ddtaquiz with time limit and password
    Given the following "activities" exist:
      | activity   | name   | intro              | course | idnumber | timelimit | ddtaquizpassword |
      | ddtaquiz       | Ddtaquiz 1 | Ddtaquiz 1 description | C1     | ddtaquiz1    | 3600      | Frog         |
    And ddtaquiz "Ddtaquiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
    When I log in as "student"
    And I am on "Course 1" course homepage
    And I follow "Ddtaquiz 1"
    And I press "Attempt ddtaquiz now"
    And I click on "Cancel" "button" in the "Start attempt" "dialogue"
    Then I should see "Ddtaquiz 1 description"
    And "Attempt ddtaquiz now" "button" should be visible

  @javascript
  Scenario: Start a ddtaquiz with time limit and password, get the password wrong first time
    Given the following "activities" exist:
      | activity   | name   | intro              | course | idnumber | timelimit | ddtaquizpassword |
      | ddtaquiz       | Ddtaquiz 1 | Ddtaquiz 1 description | C1     | ddtaquiz1    | 3600      | Frog         |
    And ddtaquiz "Ddtaquiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
    When I log in as "student"
    And I am on "Course 1" course homepage
    And I follow "Ddtaquiz 1"
    And I press "Attempt ddtaquiz now"
    And I set the field "Ddtaquiz password" to "Toad"
    And I click on "Start attempt" "button" in the "Start attempt" "dialogue"
    Then I should see "Ddtaquiz 1 description"
    And I should see "To attempt this ddtaquiz you need to know the ddtaquiz password"
    And I should see "The ddtaquiz has a time limit of 1 hour. Time will "
    And I should see "The password entered was incorrect"
    And I set the field "Ddtaquiz password" to "Frog"
    # On Mac/FF tab key is needed as text field in dialogue and page have same id.
    And I press tab key in "Ddtaquiz password" "field"
    And I press "Start attempt"
    And I should see "Text of the first question"

  @javascript
  Scenario: Start a ddtaquiz with time limit and password, get the password wrong first time then cancel
    Given the following "activities" exist:
      | activity   | name   | intro              | course | idnumber | timelimit | ddtaquizpassword |
      | ddtaquiz       | Ddtaquiz 1 | Ddtaquiz 1 description | C1     | ddtaquiz1    | 3600      | Frog         |
    And ddtaquiz "Ddtaquiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
    When I log in as "student"
    And I am on "Course 1" course homepage
    And I follow "Ddtaquiz 1"
    And I press "Attempt ddtaquiz now"
    And I set the field "Ddtaquiz password" to "Toad"
    And I click on "Start attempt" "button" in the "Start attempt" "dialogue"
    And I should see "Ddtaquiz 1 description"
    And I should see "To attempt this ddtaquiz you need to know the ddtaquiz password"
    And I should see "The ddtaquiz has a time limit of 1 hour. Time will "
    And I should see "The password entered was incorrect"
    And I set the field "Ddtaquiz password" to "Frog"
    # On Mac/FF tab key is needed as text field in dialogue and page have same id.
    And I press tab key in "Ddtaquiz password" "field"
    And I press "Cancel"
    Then I should see "Ddtaquiz 1 description"
    And "Attempt ddtaquiz now" "button" should be visible
