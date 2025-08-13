However, consider adding integration tests now for:

External service dependencies - Payment gateways, email services, file storage
Background job workflows - Queue processing, scheduled tasks, notifications
API contract testing - If other systems will consume your API
Data consistency scenarios - Complex transactions across multiple tables/services


Do NOW (before UI):

External service dependencies - Payment gateways, email services, file storage
Background job workflows - Queue processing, scheduled tasks, notifications
API contract testing - If other systems will consume your API
Data consistency scenarios - Complex transactions across multiple tables/services

Do AFTER building UI:

End-to-end user journey tests
Cross-browser compatibility tests
Performance under load tests
Any new integration scenarios the UI reveals