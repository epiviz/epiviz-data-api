# epiviz-data-api
An API for the MySQL data source template.

# Installation

1. To install, first edit the configuration file, located in `src/epiviz/epi/Config.php`:

* `VERSION`: Corresponds to the version of the Epiviz data protocol. The default is `3`.
* `DATASOURCE`: The name of your data source (can be anything).
* `DATABASE`: The exact name of your MySQL schema containing the tables corresponding to the data source.
* `SERVER`: The location of the server containing the data source. If it is located on the same machine,
you can use `localhost`.
* `USERNAME` and `PASSWORD`: The credentials for your database instance.

# Installation known issues

1. When using IIS (Windows), when first setting up the server, you see 
**HTTP Error 500.19 - Internal Server Error**. This is because of the 
*rewrite* rule in `Web.config`. To mitigate this, you need to install 
Microsoft's [**URL Rewrite Module**](http://www.iis.net/downloads/microsoft/url-rewrite).




