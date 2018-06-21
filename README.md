# Lenox Hill Premedical
This is the source code that powered the original version of Lenox Hill Premedical's web application, including the engine that deploys the full-length practice MCATs. The original mandate for this app was to "create an app that deploys simulated MCAT examinations." The business logic runs predominantly on the back end, and the app's high speed is a direct result of lightweight nature of the back-end code. The application can be viewed at https://lenox-hill-premed.appspot.com.

## Purpose
This repo is primarily for showcasing my coding style. I do not recommend cloning the app and trying to run it, as it requires numerous proprietary dependencies (data bases etc.). Virtually all of the files in this repo are free of any boilerplate code (so as to not obfuscate my own code).

## Bypassing the paywalls.
The application's paywalls are all in test mode, and can be bypassed by entering credit card information as follows:
```
Credit card number: 4242 4242 42424 42424
Expiration data: 04/24
CVC: 424
```
## ACLs
As you can see from the index.php file, the app implements some basic ACLs (Superuser, Admin, Writer, and Student). If you are interested in viewing the Writer's Portal or the Admin Console (or any other featured with privileged access), please contact me via email.
