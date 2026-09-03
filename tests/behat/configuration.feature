@tool @tool_adminagent
Feature: AI agent configuration
  In order to control the admin agent
  As an administrator
  I need to manage skills, placements and functions

  Background:
    Given I log in as "admin"
    And I navigate to "Plugins > Admin tools > AI agent" in site administration

  Scenario: Skills tab is visible
    Then I should see "AI agent configuration"
    And I should see "Skills"
    And I should see "Placements"
    And I should see "Functions"
    And I should see "Add skill"

  @javascript
  Scenario: Placements tab lists the default placement
    When I click on "Placements" "link"
    Then I should see "URL masks"
    And I should see "Floating button"

  @javascript
  Scenario: Add a skill and close
    When I click on "Add skill" "button"
    And I set the field "Short name" to "sandbox_skill"
    And I set the field "Name" to "Sandbox skill"
    And I set the field "Instructions" to "Create a sandbox course when asked."
    And I click on "Save and close" "button"
    Then I should see "Sandbox skill"

  @javascript
  Scenario: Functions tab lists catalog entries
    When I click on "Functions" "link"
    Then I should see "Function name"
    And I should see "Relevance score"
