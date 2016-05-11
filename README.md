# Deploy key generator

This CLI application simplifies the process of generating deploy keys
for git repositories.

## Background

Git allows users to authenticate using SSH public keys. While this is
superior to password authentication, most users typically only use a
single SSH key in their environment. 

The ideal situation is to use a unique key for each repository and 
deployment, with entirely different keys being used to log into machines.

## How it works

This application generates an ECDSA key suitable for SSH. After creation,
the key must be granted access on the remote git server. Github has a 
repository settings for this - users of Github will be directed to this 
page. 

Keys are saved in the folder `~/.ssh/gitdeploy`. Each repository gets 
it's own entry in `~/.ssh/config`, specifying a name for the host, the 
user/domain/port, and the key file.

Keys are encrypted using the default SSH procedure (salted password, key 
derived through single pass of md5). PKCS#8 encoding is on the wishlist, 
as it enables stretching of encryption key material. 

The tool supports unencrypted keys by setting the --no-password option.

## Usage

### Install the tool: 

This tool is available through composer. The following command will save
the application to the global composer directory (~/.composer)

    composer global require afk11/git-deploy-key
    
Update your $PATH to include ~/.composer/vendor/bin:

    echo "export PATH=~/.composer/vendor/bin:\$PATH" >> ~/.bashrc
    source ~/.bashrc # this is done automatically when you log in

### Create a password protected SSH key: 

Creating the key: 

    user@dev:~$ git-deploy-key create git@github.com:Bit-Wasp/bitcoin-php
    Confirm details: 
        [output trimmed] 
    Is this ok [y/n]: y
    Password: 
    Password again:
    You're using Github! You can paste the key in here: https://github.com/Bit-Wasp/bitcoin-php/settings/keys
    ecdsa-sha2-nistp256 AAAAE2VjZHNhLXNoYTItbmlzdHAyNTYAAAAIbmlzdHAyNTYAAABBBFz53QOkzt6ZPP1hnHY5iDqcGgLho2eZQe0h0SsAWwiwiGwT3bv6HRkKIeFeonWEH/j/QOpZee+5UyVBcMbM0Es= user@dev
    
    Saved key to disk
    
Cloning the project: 

    user@dev:~/git$ git clone github-Bit-Wasp-bitcoin-php:Bit-Wasp/bitcoin-php 
    Cloning into 'bitcoin-php'...
    Enter passphrase for key '/home/user/.ssh/gitdeploy/github-Bit-Wasp-bitcoin-php': 
         [output trimmed]
         
### Custom names for sessions

The default name for each session is made from the domain, organization, 
and repository. Custom names can be given by providing a second argument.

    $ git-deploy-key create git@github.com:Bit-Wasp/bitcoin-php gitbitcoin
    $ git clone gitbitcoin:Bit-Wasp/bitcoin-php

### Unprotected SSH key: 

The `--no-password` option can be set to disable encryption of the key. 
This can be useful during automated deployments with scheduled updates.
 
    user@dev:~$ git-deploy-key create git@github.com:Bit-Wasp/bitcoin-p2p-php --no-password
    Confirm details: 
    
        Private key file: /home/user/.ssh/gitdeploy/github-Bit-Wasp-bitcoin-p2p-php
        Public key file: /home/user/.ssh/gitdeploy/github-Bit-Wasp-bitcoin-p2p-php.pub
    
    The following config entry will be written:
    
    Host github-Bit-Wasp-bitcoin-p2p-php
    Hostname github.com
    User git
    Port 22
    IdentityFile /home/user/.ssh/gitdeploy/github-Bit-Wasp-bitcoin-p2p-php
    
    Is this ok [y/n]: y
    You're using Github! You can paste the key in here: https://github.com/Bit-Wasp/bitcoin-p2p-php/settings/keys
    ecdsa-sha2-nistp256 AAAAE2VjZHNhLXNoYTItbmlzdHAyNTYAAAAIbmlzdHAyNTYAAABBBHfgRkFAO6Du+Drrl+viCa3PxZ51N3+SMxRj2kJ6AhN6XJifXTx39rJpbUGHpyKZQvyPC1/QQNtgShktOw0JPyw= user@dev
    
    Saved key to disk
     