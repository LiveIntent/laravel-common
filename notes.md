```php
POST /posts/search
{
}

POST /posts/search
{
    page: {
        includeTotalCount: true,
    }
}

POST /posts/search
{
    page: {
        cursor: "eyJpZCI6MTUsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0",
        size: 10
    }
}

POST /posts/search
{
    page: {
        includeTotalCount: true,
        cursor: "eyJpZCI6MTUsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0",
        size: 10
    }
}

POST /posts/search
{
    page: {
        number: 2,
        size: 10
    }
}

POST /posts/search
{
    includeTotalCount: true,
    page: {
        number: 2,
        size: 10
    }
}

```

pagination stuff should be preferred in the query params
