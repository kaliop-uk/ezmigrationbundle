-- these ddl statements will cause mysl to commit any pending transaction

drop table if exists ezmb_test_table_1;

create table ezmb_test_table_1 (
    name varchar(255)
);

insert into ezmb_test_table_1 values(sysdate());
