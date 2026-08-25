variable "environment" {
  description = "Deployment environment name."
  type        = string
  default     = "production"
}

variable "project_name" {
  description = "DigitalOcean project name."
  type        = string
  default     = "sahkarai"
}

variable "region" {
  description = "DigitalOcean region slug."
  type        = string
  default     = "blr1"
}

variable "droplet_name" {
  description = "Production application droplet name."
  type        = string
  default     = "sahkarai-production"
}

variable "droplet_size" {
  description = "DigitalOcean droplet size slug."
  type        = string
  default     = "s-2vcpu-4gb"
}

variable "droplet_image" {
  description = "DigitalOcean image slug."
  type        = string
  default     = "ubuntu-24-04-x64"
}

variable "app_host" {
  description = "Canonical application hostname. Leave empty to use the reserved-IP sslip.io hostname."
  type        = string
  default     = ""
}

variable "reverb_host" {
  description = "Public Reverb hostname. Leave empty to use the reserved-IP sslip.io hostname."
  type        = string
  default     = ""
}

variable "ssh_key_fingerprints" {
  description = "DigitalOcean SSH key fingerprints installed on the droplet."
  type        = list(string)

  validation {
    condition     = length(var.ssh_key_fingerprints) > 0
    error_message = "At least one SSH key fingerprint is required."
  }
}

variable "ssh_allowed_cidrs" {
  description = "CIDRs allowed to connect to SSH. Restrict this in production."
  type        = list(string)

  validation {
    condition     = length(var.ssh_allowed_cidrs) > 0
    error_message = "At least one SSH source CIDR is required."
  }
}

variable "enable_droplet_backups" {
  description = "Enable DigitalOcean droplet snapshots in addition to application backups."
  type        = bool
  default     = true
}

variable "create_spaces_bucket" {
  description = "Create a private, versioned Spaces bucket for regulatory originals and backups."
  type        = bool
  default     = false
}

variable "spaces_bucket_name" {
  description = "Globally unique Spaces bucket name. Required when create_spaces_bucket is true."
  type        = string
  default     = ""

  validation {
    condition     = !var.create_spaces_bucket || length(var.spaces_bucket_name) >= 3
    error_message = "spaces_bucket_name is required when create_spaces_bucket is true."
  }
}
