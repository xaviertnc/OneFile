/**
 * vendors/F1/js/dropdown.js
 *
 * Simple Dropdown Component - 29 Aug 2025
 *
 * Purpose: Lightweight dropdown menu component for Bootstrap-style dropdowns
 *
 * @package F1
 * @author Claude Code <noreply@anthropic.com>
 *
 * Last 3 version commits:
 * @version 1.0 - INIT - 29 Aug 2025 - Initial dropdown component implementation
 */

(function() {
  'use strict';

  // Close all dropdowns when clicking outside
  document.addEventListener('click', function(event) {
    const dropdowns = document.querySelectorAll('.dropdown-menu.show');
    dropdowns.forEach(function(dropdown) {
      const toggle = dropdown.previousElementSibling;
      if (toggle && !toggle.contains(event.target) && !dropdown.contains(event.target)) {
        dropdown.classList.remove('show');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  });

  // Handle dropdown toggle clicks
  document.addEventListener('click', function(event) {
    const toggle = event.target.closest('[data-toggle="dropdown"]');
    if (!toggle) return;

    event.preventDefault();
    event.stopPropagation();

    const dropdown = toggle.nextElementSibling;
    if (!dropdown || !dropdown.classList.contains('dropdown-menu')) return;

    // Close other dropdowns first
    const otherDropdowns = document.querySelectorAll('.dropdown-menu.show');
    otherDropdowns.forEach(function(otherDropdown) {
      if (otherDropdown !== dropdown) {
        otherDropdown.classList.remove('show');
        const otherToggle = otherDropdown.previousElementSibling;
        if (otherToggle) otherToggle.setAttribute('aria-expanded', 'false');
      }
    });

    // Toggle current dropdown
    const isOpen = dropdown.classList.contains('show');
    if (isOpen) {
      dropdown.classList.remove('show');
      toggle.setAttribute('aria-expanded', 'false');
    } else {
      dropdown.classList.add('show');
      toggle.setAttribute('aria-expanded', 'true');
    }
  });

  // Handle escape key to close dropdowns
  document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
      const dropdowns = document.querySelectorAll('.dropdown-menu.show');
      dropdowns.forEach(function(dropdown) {
        dropdown.classList.remove('show');
        const toggle = dropdown.previousElementSibling;
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
      });
    }
  });

  // Initialize dropdown accessibility attributes
  document.addEventListener('DOMContentLoaded', function() {
    const toggles = document.querySelectorAll('[data-toggle="dropdown"]');
    toggles.forEach(function(toggle) {
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-haspopup', 'true');
    });
  });

})();