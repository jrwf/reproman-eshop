
create table orders (
    -- order
    orderId int not null primary key auto_increment,
    sessionId varchar(200) not null,
    product varchar(20),
    note text,
    -- customer
    name varchar(200),
    surname varchar(200),
    phone varchar(20),
    email varchar(250),
    -- contact address
    contactStreet varchar(250),
    contactCity varchar(250),
    contactPsc varchar(100),
    -- billing address
    billingStreet varchar(250),
    billingCity varchar(250),
    billingPsc varchar(100),
    created datetime not null default NOW(),
    lastUpdate timestamp not null default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

create table order_status (
    orderStatusId int not null primary key auto_increment,
    status varchar(200),
    lastUpdate timestamp not null default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

create table customer (
    customerId int not null auto_increment primary key,
    created datetime not null default NOW(),
    lastUpdate timestamp not null default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

create table address_contact (
    addressContactId int not null auto_increment primary key,
    street varchar(250),
    city varchar(250),
    psc varchar(100),
    created datetime not null default NOW(),
    lastUpdate timestamp not null default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

create table address_billing (
     addressBillingId int not null auto_increment primary key,
     street varchar(250),
     city varchar(250),
     psc varchar(100),
     created datetime not null default NOW(),
     lastUpdate timestamp not null default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

/*
create table orders (
    orderId int not null primary key auto_increment,
    product varchar(20),
    note text,
    created datetime not null default NOW(),
    lastUpdate timestamp not null default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
*/

alter table orders add column delivery varchar(10) after contactPsc;
alter table orders add column state varchar(10) after delivery;
alter table orders add column billingAddress varchar(250) after billingStreet;
alter table orders add column packetaAttribute varchar(250) after packetaId;
alter table orders add column deliveryId int(5) after delivery;
alter table orders add column houseNumber varchar(250) after contactStreet;
alter table orders add column orderIdGp int (20) after orderId;
