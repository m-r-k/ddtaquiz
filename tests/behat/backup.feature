@mod @mod_ddtaquiz
Feature: Backup and restore of ddtaquizzes
  In order to reuse my ddtaquizzes
  As a teacher
  I need to be able to back them up and restore them.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And I log in as "admin"

  @javascript
  Scenario: Duplicate a ddtaquiz with two questions
    Given the following "activities" exist:
      | activity   | name   | intro              | course | idnumber |
      | ddtaquiz       | Ddtaquiz 1 | For testing backup | C1     | ddtaquiz1    |
    And the following "questions" exist:
      | questioncategory | qtype       | name | questiontext    |
      | Test questions   | truefalse   | TF1  | First question  |
      | Test questions   | truefalse   | TF2  | Second question |
    And ddtaquiz "Ddtaquiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
      | TF2      | 2    |
    When I am on "Course 1" course homepage with editing mode on
    And I duplicate "Ddtaquiz 1" activity editing the new copy with:
      | Name | Ddtaquiz 2 |
    And I follow "Ddtaquiz 2"
    And I navigate to "Edit ddtaquiz" in current page administration
    Then I should see "TF1"
    And I should see "TF2"

  @javascript @_file_upload
  Scenario: Restore a Moodle 2.8 ddtaquiz backup
    When I am on "Course 1" course homepage
    And I navigate to "Restore" node in "Course administration"
    And I press "Manage backup files"
    And I upload "mod/ddtaquiz/tests/fixtures/moodle_28_ddtaquiz.mbz" file to "Files" filemanager
    And I press "Save changes"
    And I restore "moodle_28_ddtaquiz.mbz" backup into "Course 1" course using this options:
    And I follow "Restored Moodle 2.8 ddtaquiz"
    And I navigate to "Edit ddtaquiz" in current page administration
    Then I should see "TF1"
    And I should see "TF2"
