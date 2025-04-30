# Database Plugin

This WordPress plugin reads CSV data and displays it as a searchable, filterable resource database.

## Features

- **CSV Import**: Automatically reads data from a CSV file in the plugin directory
- **Responsive Table**: Display resources in a clean, responsive table format
- **Search**: Search across all resource entries
- **Tag Filtering**: Filter resources by clicking on tags
- **Pagination**: Navigate through resources with built-in pagination
- **Mobile Responsive**: Works well on all device sizes

## Usage

1. Place your data in the `crisisResources.csv` file in the plugin directory
2. Add the shortcode `[displayResources]` to any page or post where you want the database to appear

## CSV Format

The CSV file should have the following columns:

1. Resource Name (required)
2. Phone Number (optional)
3. Description (required)
4. Keywords (comma-separated tags for filtering)
5. Website URL (optional)

Example:
