Related Posts
=============

This plugin provides a simple related posts function with the following features:

* ElasticPress integration (uses a "More Like This" query)
* Admin UI for manually overriding related posts

## Installation

```
composer require humanmade/related-posts
```

## Usage

The plugin exposes a single function that returns a list of post IDs.

### `HM\Related_Posts\get( int $post_id, array $args = [] )`

**$post_id** is the ID of the post to get related content for.

**$args** allows you some control over the posts that are returned:

- int **limit**: defaults to `10`
- array **post_types**: array of post types to limit results to. Defaults to `[ 'post' ]`
- array **taxonomies**: array of taxonomies to compare against. Defaults to `[ 'category' ]`
- array **terms**: array of `WP_Term` objects, results will match these terms
- array **terms_not_in**: array of `WP_Term` objects, results will not match these terms
- bool **ep_integrate**: if true then ElasticPress is used to get the results, Defaults to `defined( 'EP_VERSION' )`

---------------------

Made with ❤️ by [Human Made](https://humanmade.com)
