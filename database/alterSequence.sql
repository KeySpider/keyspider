/* RoleToPrivilege */
create sequence "RoleToPrivilege_ID_seq";

alter table "RoleToPrivilege" alter "ID" set default nextval('"RoleToPrivilege_ID_seq"');

alter sequence "RoleToPrivilege_ID_seq" owned by "RoleToPrivilege"."ID";

select setval('"RoleToPrivilege_ID_seq"', 1, false);

/* UserToGroup */
create sequence "UserToGroup_ID_seq";

alter table "UserToGroup" alter "ID" set default nextval('"UserToGroup_ID_seq"');

alter sequence "UserToGroup_ID_seq" owned by "UserToGroup"."ID";

select setval('"UserToGroup_ID_seq"', 1, false);

/* UserToOrganization */
create sequence "UserToOrganization_ID_seq";

alter table "UserToOrganization" alter "ID" set default nextval('"UserToOrganization_ID_seq"');

alter sequence "UserToOrganization_ID_seq" owned by "UserToOrganization"."ID";

select setval('"UserToOrganization_ID_seq"', 1, false);

/* UserToPrivilege */
create sequence "UserToPrivilege_ID_seq";

alter table "UserToPrivilege" alter "ID" set default nextval('"UserToPrivilege_ID_seq"');

alter sequence "UserToPrivilege_ID_seq" owned by "UserToPrivilege"."ID";

select setval('"UserToPrivilege_ID_seq"', 1, false);

/* UserToRole */
create sequence "UserToRole_ID_seq";

alter table "UserToRole" alter "ID" set default nextval('"UserToRole_ID_seq"');

alter sequence "UserToRole_ID_seq" owned by "UserToRole"."ID";

select setval('"UserToRole_ID_seq"', 1, false);
