@tool @tool_adminagent @javascript
Feature: AI agent chat chrome
  In order to ask the agent to do work
  As an administrator
  I need to open the chat widget and approve function calls

  Background:
    Given I log in as "admin"

  Scenario: Floating button opens resume or welcome
    When I am on homepage
    Then "button.tool-adminagent-fab" "css_element" should exist
    When I click on "button.tool-adminagent-fab" "css_element"
    Then I should see "Welcome"

  Scenario: Overlay shows auto-approve options
    When I am on homepage
    And I click on "button.tool-adminagent-fab" "css_element"
    And I click on "Auto-approve: None" "button"
    Then I should see "Ask before every function call"
    And I should see "Read-only"

    Scenario: Send a message and approve a function call
    When I am on homepage
    And I click on "button.tool-adminagent-fab" "css_element"
    And I set the field "Type a message..." to "List the functions I can use"
    And I click on "Send" "button"
    Then I should see "search_tools"
    And I should see "get_unix_timestamp"
    And I should see "Approve"
    When I click on "Approve" "button"
    Then I should see "The test driver finished that request."
